<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class IsAdult extends Constraint
{
    public $message = 'Vous devez avoir au moins 18 ans pour vous inscrire à HaloGari. Veuillez vérifier votre date de naissance.';

}
