<?php
declare(strict_types=1);
namespace KarlixLaravel\Http\Requests;

/** اعتبارسنجی نوع ورودی در Laravel؛ قواعد دامنه/مجوز در Service نگه داشته می‌شوند. */
final class PanelRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $rules=[];
        foreach(['title','description','note','content','mobile','email','password','project_title','project_description','message','q','due_date','due_time','repeat_kind','body','mode','client_ref','action','ref'] as $name) { $rules[$name]=['sometimes','nullable','string']; }
        return $rules;
    }
    public function messages(): array { return ['string'=>'فیلد «:attribute» باید متن باشد.']; }
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \App\Core\HttpException($this->panel()->isApi()?400:422,$validator->errors()->first(),'INVALID_INPUT');
    }
    protected function prepareForValidation(): void { $this->attributes->set('panel', \App\Core\Request::capture()); }
    public function panel(): \App\Core\Request { return \App\Core\Request::capture(); }
}
