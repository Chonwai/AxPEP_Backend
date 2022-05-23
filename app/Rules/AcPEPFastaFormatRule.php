<?php

namespace App\Rules;

use App\Utils\FormatUtils;
use Illuminate\Contracts\Validation\Rule;

class AcPEPFastaFormatRule implements Rule
{
    private $error;

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
            $flag = FormatUtils::checkAcPEPFASTAFormat($value);
        } elseif ($attribute == 'file') {
            $data = file_get_contents($value->getRealPath());
            $flag = FormatUtils::checkAcPEPFASTAFormat($data);
        }
        if ($flag !== true) {
            $this->error = $flag;
            return false;
        } else {
            return $flag;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->error;
    }
}
