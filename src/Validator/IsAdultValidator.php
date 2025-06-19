<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsAdultValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        /* @var $constraint \App\Validator\IsAdult */

        if (!$value instanceof \DateTimeInterface) {
            return; // on ne valide que les objets date
        }

        $now = new \DateTime();
        $age = $now->diff($value)->y;

        if ($age < 18) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
