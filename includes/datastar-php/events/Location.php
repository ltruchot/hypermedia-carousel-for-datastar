<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace HCFD\Datastar\events;

defined( 'ABSPATH' ) || exit;

class Location extends ExecuteScript
{
    public function __construct(string $uri, array $options = [])
    {
        $this->script = "setTimeout(() => window.location = '$uri')";

        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
