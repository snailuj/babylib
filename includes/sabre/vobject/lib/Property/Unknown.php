<?php

namespace Sabre\VObject\Property;

/**
 * Unknown property.
 *
 * This object represents any properties not recognized by the parser.
 * This type of value has been introduced by the jCal, jCard specs.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Unknown extends Text {

    /**
     * Returns the value, in the format it should be encoded for json.
     *
     * This method must always return an array.
     *
     * @return array
     */
    function getJsonValue() {

        return [$this->getJsonFriendlyMimeDirValue()];

    }

    /**
     * JSJSJS
     * I want to send Non-Standard Properties with JSON string values to the client
     * as part of a VEVENT object. Non-Standard props are handled by this "Unknown" class
     * in SabreDAV.
     *
     * JSON strings in Property values are being stored in the DB by SabreDAV as (e.g.)
     * "{\"location\":\"The Domain\"\\,\"meetingTime\":\"05:30:00 GMT+1000\"\\,\"priceAUD\":220\\,\"paxMax\":32}"
     *
     * (note the "\\," parts of that string)
     *
     * Now, I know that the iCalendar spec says that Property values MUST have escaped commas.
     * But this is a prob for me because when I try to parse the prop value in Javascript,
     * JSON.parse barfs if it gets escaped commas like the above.
     * 
     * Not sure where the jCal spec stands on this, but I suppose it has the same requirement about escaping
     * commas. However, I don't imagine there is actually any need for that, since in contrast to an actual
     * iCalendar file commas hold no special meaning in JSON?
     *
     * Whatever, I don't have time to fuck around with it. So I'm doing a nasty hack to translate 
     * literal-backslashes that precede commas into just plain
     * commas.
     */
    function getJsonFriendlyMimeDirValue() {
        // copied from Text.php but removing the comma escaping
        $val = $this->getParts();

        if (isset($this->minimumPropertyValues[$this->name])) {
            $val = array_pad($val, $this->minimumPropertyValues[$this->name], '');
        }

        foreach ($val as &$item) {

            if (!is_array($item)) {
                $item = [$item];
            }

            foreach ($item as &$subItem) {
                $subItem = strtr(
                    $subItem,
                    [
                        '\\' => '\\\\',
                        ';'  => '\;',
                        "\n" => '\n',
                        "\r" => "",
                    ]
                );
            }
            $item = implode(',', $item);

        }

        return implode($this->delimiter, $val);

    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     *
     * @return string
     */
    function getValueType() {

        return 'UNKNOWN';

    }

}
