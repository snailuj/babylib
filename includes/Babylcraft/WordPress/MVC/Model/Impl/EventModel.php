<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Babylcraft\WordPress\MVC\Model\ModelException;
use Babylcraft\WordPress\MVC\Model\FieldException;
use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\IUniqueModelIterator;
use Babylcraft\WordPress\MVC\Model\Sabre\IVObjectFactory;

class EventModel extends BabylonModel implements IEventModel
{
    /**
     * @var bool
     */
    private $isVariation = false;

    protected $vobjectFactory;

    #region static

    /**
     * This class represents either events (equivalent to VEVENTs objects in ICal parlance) or event
     * variations (equiv to EXRULEs).
     * 
     * @param IBabylonModel $parent The parent of this event, which could be either an `ICalendarModel`
     * if this model represents a VEVENT, or else an `IEventModel` if this model represents an EXRULE
     * @param string $name The name of this event (which will be used as the URI and must be unique
     * within $parent).
     * @param string $rrule The recurrence rule for this event; use empty string if there is no
     * recurrence.
     * @param [\DateTimeInterface] $start The start date and time for the event; note that this is nullable
     * for `EventModel`s that represent an EXRULE, but is required by the ICal spec for VEVENTs
     * @param string $uid Unique identifier for this `EventModel` object
     */
    public static function construct(
        IBabylonModel $parent,
        string $name,
        string $rrule,
        ?\DateTimeInterface $start,
        string $uid
    ) : IEventModel
    {
        $new = new static();
        $new->setParentType(get_class($parent));
        $new->setParent($parent);

        $fields = [
            static::F_RRULE => $rrule
        ];

        if( $parent instanceof ICalendarModel ) {
            $new->isVariation = false;
            $fields[static::F_START] = $start;
        } else {
            $new->isVariation = true;
        }

        $new->setValues($fields);
        $new->setReadonlyValues([
            static::F_NAME => $name,
            static::F_UID  => $uid
        ]);
        
        return $new;
    }

    static public function getSchema(
        string $tableNamespace,
        string $wpTableNamespace,
        string $charsetCollate,
        bool $drop = false) : string
    {
        return "";
    }
    #endregion

    #region IVObjectClient implementation
    public function setVObjectFactory(IVObjectFactory $factory)
    {
       $this->vobjectFactory = $factory;
    }
    #endregion

    #region IEventModel Implementation
    public function addVariation(string $name, string $rrule, string $uid = '') : IEventModel
    {
        return $this->addVariationModel($this->getModelFactory()->newVariation($this, $name, $rrule, $uid));
    }

    public function addNewVariation(string $name, string $rrule) : IEventModel
    {
        return $this->addVariation($name, $rrule, '');
    }

    public function isVariation() : bool
    {
        return $this->isVariation;
    }

    public function getVariations() : IUniqueModelIterator
    {
        return $this->getChildIterator(IEventModel::class);
    }

    public function getVariation(string $uid) : ?IEventModel
    {
        return $this->getVariations()[$uid] ?? null;
    }

    protected function addVariationModel(IEventModel $variation) : IEventModel
    {
        $this->addChild($this->getChildKey($variation), $variation);
        $test = $this->vobjectFactory->variationToExrule(
            $variation,
            $this->getParent()->getEventAsVEvent($this->getValue(static::F_UID))
        );
        
        return $variation;
    }
    #endregion

    #region overrides
    /**
     * For EventModels that are variations (aka EXRULEs in iCalendar-speak), we provide for
     * storing 'uri' and 'uid' as "non-standard PARAMETERS" on the EXRULE.
     * 
     * For EventModels that are NOT variations, we do store values as regular properties.
     */
    public function getSerializable($byFieldPack = 0): array
    {
        $map = [];
        if ( $this->isVariation ) {
            if ( !$byFieldPack || ($byFieldPack & static::F_RRULE != 0) ) {
                // Variations are stored as EXRULEs, so we need to tweak this so it can be 
                // handed over the fence to Sabre CalDAV.
                // see comments on SabreFacade::createEvent()
                // I know, this is weird indexing
                // TODO remove magic numbers
                $map[0] = $this->getValue(static::F_RRULE);
                $map[1] = [
                        $this->getFieldName(static::F_UID)  => $this->getValue(static::F_UID),
                        $this->getFieldName(static::F_NAME) => $this->getValue(static::F_NAME)
                ];
            }
        } else {
            if ( !$byFieldPack || ($byFieldPack & static::F_RRULE != 0) ) {
                $map[$this->getFieldName(static::F_RRULE)] = $this->getValue(static::F_RRULE);
            }
            
            if ( !$byFieldPack || ($byFieldPack & static::F_UID != 0) ) {
                $map[$this->getFieldName(static::F_UID)] = $this->getValue(static::F_UID);
            }

            if ( !$byFieldPack || ($byFieldPack & static::F_NAME != 0) ) {
                $map[$this->getFieldName(static::F_NAME)] = $this->getValue(static::F_NAME);
            }

            if ( !$byFieldPack || ($byFieldPack & static::F_START != 0) ) {
                $map[$this->getFieldName(static::F_START)] = $this->getValue(static::F_START);
            }
        }

        return $map;
    }

    protected function setupFields() : void
    {
        parent::setupFields();
        $this->addFields(static::EVENT_FIELDS);
        $this->setParentType(ICalendarModel::class);

        //
        // this line removes F_ID from persistence / serialize calls -- CalDAV and client-side code 
        // both use the URI to identify events so there's no need to clutter the JSON with IDs
        //
        unset($this->fields[static::F_ID][static::K_NAME]);
    }

    protected function isDirty() : bool
    {   //EXRULEs are saved to the table-row of their enclosing VEvent
        //no need for, nor way to accomplish, individual saving
        return $this->dirty && !$this->isVariation();
    }

    protected function getChildKey(IBabylonModel $variation)
    {
        if ( $variation instanceof IEventModel ) {
            return $variation->getValue(static::F_NAME);
        }

        throw new FieldException(FieldException::ERR_WRONG_TYPE, "given child is not an IEventModel.");
    }
    #endregion
}