<?php
namespace Lms\Exceptions;

class NotFoundException extends \RuntimeException
{
    protected $code = 404;
}
