<?php

namespace App\Http\Requests;

use App\Rules\FastaFormatRule;
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
            'id' => ['required', 'exists:tasks,id'],
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
            'fasta' => ['required', new FastaFormatRule],
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
            'file' => ["required", "file", "mimes:txt", new FastaFormatRule],
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
    public static function codonRules()
    {
        return [
            'file' => ["required", "file", "mimes:txt"],
            'codon' => 'required',
        ];
    }
}
