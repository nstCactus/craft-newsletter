<?php

namespace juban\newsletter\events;

use craft\events\ModelEvent;

class SubscribeEvent extends ModelEvent
{
    public bool $isSpam = false;
    public bool $isNew = true;
}
