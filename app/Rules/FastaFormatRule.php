<?php

namespace App\Rules;

use App\Utils\FormatUtils;
use App\Utils\Utils;
use Illuminate\Contracts\Validation\Rule;

class FastaFormatRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($attribute == 'fasta') {
            $flag = FormatUtils::checkFASTAFormat($value);
        }
        return $flag;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The Fasta format is not correct.';
    }
}
