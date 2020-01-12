<?php

namespace Larabookir\Gateway;

interface Restable {

    public function getGatewayUrl();

    public function redirectParameters();
}
