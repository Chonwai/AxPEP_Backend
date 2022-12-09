<?php

namespace App\Http\Requests;

use App\Rules\AcPEPFastaFormatRule;
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
    public static function acpepTextareaRules()
    {
        return [
            'fasta' => ['required', new AcPEPFastaFormatRule],
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
    public static function acpepFileRules()
    {
        return [
            'file' => ["required", "file", "mimes:txt", new AcPEPFastaFormatRule],
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public static function textareaSMIRules()
    {
        return [
            'smi' => ['required'],
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public static function fileSMIRules()
    {
        return [
            'file' => ["required", "file", "mimes:txt"],
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
