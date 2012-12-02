<?php

namespace Aperophp\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\DateValidator;

class FutureDateValidator extends DateValidator
{

    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        parent::validate($value, $constraint);

        if ($value instanceof \DateTime) {
            $now = new \DateTime('today');
            if ($value < $now) {
                $this->context->addViolation($constraint->message, array('%date%' => $value));
            }
        }

        if (is_string($value) && date('Y-m-d', strtotime($value)) < date('Y-m-d', time())) {
            $this->context->addViolation($constraint->message, array('%date%' => $value));
        }
    }
}
