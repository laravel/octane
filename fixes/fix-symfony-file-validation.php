<?php

// https://github.com/avto-dev/roadrunner-laravel/issues/10
// https://github.com/spiral/roadrunner/issues/133

namespace Symfony\Component\HttpFoundation\File;

function is_uploaded_file($filename)
{
    return true;
}
