<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Babylcraft\WordPress\MVC\Model\ModelException;
use Sabre\CalDAV\Backend;
use Sabre\VObject;
use Babylcraft\WordPress\MVC\Model\FieldException;
use Babylcraft\WordPress\MVC\Model\SabreFacade;
use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\IUniqueModelIterator;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property\ICalendar\Recur;


class EventModel extends BabylonModel implements IEventModel
{
    /**
     * @var VEvent Underlying VObject representation of this event. For delegating all the crazy
     * recurrence logic to.
     */
    private $vevent;

    #region static
    static public function getSchema(
        string $tableNamespace,
        string $wpTableNamespace,
        string $charsetCollate,
        bool $drop = false) : string
    {
        return "";
    }

    //if $start is null, assumes we are creating a variation (because EXRULE doesn't support DTSTART parameters)
    //in that case, we will generate a UID for the variation (EXRULEs are all serialized into a single column on
    //the VEvent row in calendarobjects table, so we need something to reference them later)
    // static protected function makeEvent(
    //     IBabylonModel $parent,
    //     string $name,
    //     string $rrule,
    //     \DateTimeInterface $start = null,
    //     string $uid = null
    // ) : IEventModel {
    //     $fields = [
    //         static::F_NAME   => $name,
    //         static::F_RRULE  => $rrule,
    //         static::F_START  => $start,
    //         static::F_PARENT => $parent
    //     ];

    //     $event = new static();

    //     if (!$start) {
    //         $event->isVariation = true;
    //         $event->setReadOnlyValue( static::F_UID, $uid ?? \Babylcraft\Util::generateUid() );
    //     }

    //     $event->setParentType(get_class($parent));
    //     $event->setValues($fields);

    //     return $event;
    // }
    #endregion

    #region IEventModel Implementation
    /**
     * @var bool
     */
    private $isVariation = false;

    /**
     * @var callable Method to call when retrieving the underlying VEvent or Recur VObject
     */
    private $getVObject;

    /**
     * This class represents either Events (equivalent to VEvent objects in ICal parlance) or Event 
     * variations (equiv to Recur objects).
     */
    public static function construct(IBabylonModel $parent, callable $getVObject)
    {
        $new = new static();
        $new->setParentType(get_class($parent));
        $new->setParent($parent);
        $new->getVObject = $getVObject;

        if ($vevent) {
            $new->vevent = $vevent;
            $new->setValues([
                static::F_RRULE  => $vevent->RRULE->getValue(),
                static::F_NAME   => $vevent->SUMMARY->getValue(),
                static::F_START  => $vevent->DTSTART->getDateTime()
            ]);

            $new->setReadOnlyValue(static::F_UID, $vevent->UID->getValue());
        } else {
            $new->isVariation = true;
            $new->vevent = $parent->vevent;
            $new->setValue( static::F_NAME, $exrule->parameters()['URI']->getValue() );

            $new->setReadOnlyValue( static::F_UID, $exrule->parameters()['UID']->getValue() );
        }

        return $new;
    }

    public function addVariation(string $name, string $rrule, array $fields = []) : IEventModel
    {
        return $this->addVariationModel($this->getModelFactory()->newVariation($this, $name, $rrule, $fields));
    }

    public function isVariation() : bool
    {
        return $this->isVariation;
    }

    public function getVariations() : IUniqueModelIterator
    {
        return $this->getChildIterator(IEventModel::class);
    }

    public function addVariationModel(IEventModel $variation) : IEventModel
    {
        $this->addChild($variation->getValue(static::F_NAME), $variation);

        return $variation;
    }

    public function toVEvent() : VObject\Component\VEvent
    {
        if ($this->isVariation()) {
            //use the parent Event as the vevent
            $this->vevent = $this->getParent()->toVEvent();
        }

        if (!$this->vevent) {
            $this->vevent = $this->getParent()->toVCalendar()->create(
                "VEVENT",
                $this->eventToCalDAV(),
                $defaults = false
            );
        }

        return $this->vevent;
    }

    protected function eventToCalDAV() : array
    {
        $caldav = [
            "SUMMARY" => $this->getValue(static::F_NAME), //this is also the uri in DB
            "DTSTART" => $this->getValue(static::F_START)
        ];

        $rrule = $this->getValue(static::F_RRULE);
        if ($rrule) {
            $caldav["RRULE"] = $rrule;
        }

        return array_merge($caldav, $this->propsToCalDAV());
    }

    /**
     * This function returns the properties that are to be saved as Non-Standard Properties
     * (see the iCalendar RFC) on the VEVENT / EXRULE.
     * 
     * For EventModels that are variations (aka EXRULEs in iCalendar-speak), we provide for
     * storing 'uri' and 'uid' properties.
     * 
     * For EventModels that are NOT variations, we do not store any non-standard props.
     */
    protected function propsToCalDAV() : array
    {
        if ($this->isVariation()) {
            return [
                $this->getFieldName(static::F_UID)  => $this->getValue(static::F_UID),
                $this->getFieldName(static::F_NAME) => $this->getValue(static::F_NAME)
            ];
        }

        return [];
    }

    protected function variationsToCalDAV() : array
    {
        $caldav = [];
        foreach( $this->getVariations() as $variation ) {
            $caldav[] = $this->variationToCalDAV($variation);
        }

        return $caldav;
    }

    protected function variationToCalDAV(IEventModel $variation) : array
    {
        return [
            "EXRULE"   => $variation->getValue(static::F_RRULE),
            "children" => $variation->propsToCalDAV()
        ];
    }
    #endregion

    #region overrides
    protected function setupFields() : void
    {
        parent::setupFields();
        $this->addFields(static::EVENT_FIELDS);
        $this->setParentType(ICalendarModel::class);
        $this->setFieldType(static::F_ID, static::T_STRING); //override F_ID to string (because it's a UID)

        //
        // this line removes ID from persistence / serialize calls -- CalDAV and client-side code both rely
        // on the URI to identify events
        //
        unset($this->fields[static::F_ID][static::K_NAME]);
    }

    protected function isDirty() : bool
    {   //EXRULEs are saved to the table-row of their enclosing VEvent
        //no need for, nor way to accomplish, individual saving
        return $this->dirty && !$this->isVariation();
    }

    protected function doGetValue(int $field)
    {
        if ($this->isVariation) {
            return $this->getValueFromRecurObject($field, $this->getVObject());
        }

        return $this->getValueFromVEventObject($field, $this->getVObject());
    }

    protected function getValueFromRecurObject(int $field, Recur $recur)
    {
        switch ($field) {
            case static::F_ID:
                return $recur->parameters()['UID']->getValue();
            case static::F_NAME:
                return $recur->parameters()['URI']->getValue();
            case static::F_RRULE:
                return $recur->getValue();
            default:
                throw new FieldException(FieldException::ERR_NOT_FOUND, "field $field");
        }
    }

    protected function getValueFromVEventObject(int $field, VEvent $vevent)
    {
        switch ($field) {
            case static::F_ID:
                return $vevent->UID->getValue();
            case static::F_NAME:
                return $vevent->SUMMARY->getValue();
            case static::F_RRULE:
                return $vevent->RRULE->getValue();
            default:
                throw new FieldException(FieldException::ERR_NOT_FOUND, "field $field");
        }
    }

    protected function doCreateRecord() : bool
    {
        static::createRecordFor($this);

        return true;
    }

    static protected function createRecordFor(IEventModel $event) : void
    {
        throw new ModelException(ModelException::ERR_OTHER, "mf-refactory not done yet");
    }

    protected function doUpdateRecord() : bool
    {
        \Babylcraft\WordPress\PluginAPI::debug("doUpdateRecord() for: ". $this->getValue(static::F_NAME));
        return true;
        // throw new \Exception("Not implemented yet!");
    }
    #endregion
}