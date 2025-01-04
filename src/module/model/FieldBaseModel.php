<?php
declare (strict_types=1);

namespace mowzs\lib\module\model;

use mowzs\lib\Model;

abstract class FieldBaseModel extends Model
{
    protected $json = ['extend'];
    protected $jsonAssoc = true;
}
