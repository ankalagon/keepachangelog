<?php

namespace Ankalagon\KeepAChangeLog;

Interface DecoratorInterface {
    public function render(Changelog $changelog);
}