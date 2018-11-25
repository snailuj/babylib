<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Babylcraft\WordPress\MVC\Model\ModelException;
use Babylcraft\WordPress\MVC\Model\FieldException;
use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\IUniqueModelIterator;
use Babylcraft\WordPress\MVC\Model\Sabre\IVObjectFactory;
use Sabre\VObject\Node;
use Sabre\VObject\Recur\RRuleIterator;

class EventModel extends BabylonModel implements IEventModel
{
    /**
     * @var bool
     */
    private $isVariation = false;

    /**
     * @var IVObjectFactory Injected by the model factory, used for creating EXRULEs whenever a 
     * variation is added
     */
    protected $vobjectFactory;

    /**
     * @var RRuleIterator Cached iterator for this event model's recurrence rule
     */
    protected $rruleIterator;

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
     */
    public static function construct(
        IBabylonModel $parent,
        string $name,
        string $rrule,
        ?\DateTimeInterface $start
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
        $new->setReadonlyValue(static::F_NAME, $name);
        
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
    public function addVariation(string $name, string $rrule) : IEventModel
    {
        return $this->addVariationModel($this->getModelFactory()->newVariation($this, $name, $rrule));
    }

    public function isVariation() : bool
    {
        return $this->isVariation;
    }

    public function getVariations() : IUniqueModelIterator
    {
        if ($this->isVariation()) {
            throw new ModelException(ModelException::ERR_WRONG_TYPE, "Cannot get variations from a variation.");
        }

        return $this->getChildIterator(IEventModel::class);
    }

    public function getVariation(string $name) : ?IEventModel
    {
        return $this->getVariations()[$name] ?? null;
    }

    public function asVObject() : Node
    {
        $node = $this->getParent()->getEventAsVObject($this->getValue(static::F_NAME));
        if (!$node) {
            throw new ModelException(ModelException::ERR_RECORD_NOT_FOUND, "Missing VObject with my name in parent->asVCalendar()");
        }

        return $node;
    }

    public function isInTimerange(\DateTimeInterface $startDate, \DateInterval $interval): bool
    {
        for ($iter = $this->getRRuleIterator($startDate), 
              $end = $startDate->add($interval); $iter->current < $end && $iter->valid(); $iter->next()) {
                return true; //if we get here, we've found a match
        }

        return false;
    }

    protected function addVariationModel(IEventModel $variation) : IEventModel
    {
        if ($this->isVariation()) {
            throw new ModelException(ModelException::ERR_WRONG_TYPE, "Cannot add variation to a variation.");
        }

        $this->addChild($this->getChildKey($variation), $variation);
        $this->vobjectFactory->copyToVEvent(
                $variation,
                $this->asVObject()
        );
        
        return $variation;
    }

    protected function getRRuleIterator(\DateTimeInterface $startDate = null) : RRuleIterator
    {
        if (null === $this->rruleIterator 
            || $this->getValue(static::F_RRULE) != $this->rruleIterator->getRRule()) {
            $this->rruleIterator = new RRuleIterator($this->getValue(static::F_RRULE));
        }

        if ($startDate) {
            $this->rruleIterator->setStartDate($startDate);
        }

        return $this->rruleIterator;
    }
    #endregion

    #region overrides
    public function isDirty() : bool
    {   //EXRULEs are saved to the table-row of their enclosing VEvent
        //no need for, nor way to accomplish, individual saving
        return $this->dirty && !$this->isVariation();
    }

    /**
     * For EventModels that are variations (aka EXRULEs in iCalendar-speak), we provide for
     * storing 'uri' as a "non-standard PARAMETER" on the EXRULE.
     * 
     * For EventModels that are NOT variations, we store values as regular properties.
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
                    $this->getFieldName(static::F_NAME) => $this->getValue(static::F_NAME)
                ];
            }
        } else {
            if ( !$byFieldPack || ($byFieldPack & static::F_RRULE != 0) ) {
                $map[$this->getFieldName(static::F_RRULE)] = $this->getValue(static::F_RRULE);
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

    protected function getChildKey(IBabylonModel $variation)
    {
        if ( $variation instanceof IEventModel ) {
            return $variation->getValue(static::F_NAME);
        }

        throw new FieldException(FieldException::ERR_WRONG_TYPE, "given child is not an IEventModel.");
    }
    #endregion
}