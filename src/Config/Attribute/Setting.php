<?php

namespace App\Config\Attribute;

/**
 * Attach metadata to a setting key.
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
class Setting
{
    /**
     * @param string      $type    one of: bool,string,int,float,date,datetime,html
     * @param string      $group   logical group/prefix, e.g. "maintenance"
     * @param mixed       $default default value to use when DB is empty
     * @param string|null $label   translation key or plain string
     * @param string|null $help    translation key or plain string
     */
    public function __construct(
        public string $type,
        public string $group,
        public mixed $default = null,
        public ?string $label = null,
        public ?string $help = null,
    ) {
    }
}
