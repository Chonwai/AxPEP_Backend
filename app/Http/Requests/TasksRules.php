<?php

namespace App\Http\Requests;

use App\Rules\FastaRule;
use Illuminate\Foundation\Http\FormRequest;

class TasksRules extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public static function rules()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public static function textareaRules()
    {
        return [
            'fasta' => 'required',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public static function fileRules()
    {
        return [
            'file' => ["required", "file", "mimes:txt", new FastaRule],
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public static function emailRules()
    {
        return [
            'email' => 'required|email',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public static function fileAndCodonRules()
    {
        return [
            'file' => ["required", "file", "mimes:txt", new FastaRule],
            'codon' => 'required',
        ];
    }
}
