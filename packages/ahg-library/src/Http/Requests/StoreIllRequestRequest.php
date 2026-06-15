<?php

namespace AhgLibrary\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIllRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'ill_number'          => 'nullable|string|max:30',
            'requester_library_id'=> 'nullable|integer|exists:library_vendor,id',
            'responder_library_id'=> 'nullable|integer|exists:library_vendor,id',
            'trading_partner_id'  => 'nullable|integer|exists:library_trading_partner,id',
            'patron_id'           => 'nullable|integer',
            'request_type'        => ['nullable', Rule::in(['BORROW', 'SUPPLY', 'PHOTOCOPY', 'LOAN_RENEWAL', 'STATUS_CHECK'])],
            'borrowing_protocol'  => ['nullable', Rule::in(['AARC', 'IFM', 'BLDSS', 'RLG', 'CUSTOM'])],
            'material_type'       => ['nullable', Rule::in(['BOOK', 'SERIAL_ISSUE', 'CONFERENCE_PAPER', 'THESIS', 'PATENT', 'REPORT', 'OTHER'])],
            'isbn'                => ['nullable', 'regex:/^[0-9\-Xx]{10,17}$/'],
            'issn'                => ['nullable', 'regex:/^[0-9\-Xx]{8,10}$/'],
            'oclc_number'         => 'nullable|string|max:30',
            'title'               => 'required|string|max:500',
            'author'              => 'nullable|string|max:300',
            'publisher'           => 'nullable|string|max:200',
            'publication_year'    => 'nullable|string|max:10',
            'volume'              => 'nullable|string|max:50',
            'issue'               => 'nullable|string|max:30',
            'pages'               => 'nullable|string|max:50',
            'citation'            => 'nullable|string|max:500',
            'lender_string'       => 'nullable|string',
            'request_date'        => 'nullable|date',
            'needed_by_date'      => 'nullable|date|after_or_equal:request_date',
            'request_status'      => ['nullable', Rule::in([
                'pending', 'requested', 'shipped', 'received', 'returned',
                'lost', 'cancelled', 'overdue', 'unfulfilled',
            ])],
            'requester_note'      => 'nullable|string|max:2000',
            'responder_note'      => 'nullable|string|max:2000',
            'cost_amount'         => 'nullable|numeric|min:0|max:999999.99',
            'cost_currency'       => 'nullable|string|size:3',
            'shipping_method'     => 'nullable|string|max:50',
            'tracking_number'     => 'nullable|string|max:100',
            'due_date'            => 'nullable|date',
            'max_renewals'        => 'nullable|integer|min:0|max:10',
            'edi_message_id'      => 'nullable|string|max:50',
            'closed_reason'       => 'nullable|string|max:200',
            // Phase-1 compat
            'type'                => 'nullable|in:borrow,lend',
            'library_name'        => 'nullable|string|max:300',
            'library_symbol'      => 'nullable|string|max:50',
            'notes'               => 'nullable|string|max:5000',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('request_date')) {
            $this->merge(['request_date' => now()->toDateString()]);
        }
        if (!$this->filled('request_status')) {
            $this->merge(['request_status' => 'pending']);
        }
        if (!$this->filled('type')) {
            $this->merge(['type' => 'borrow']);
        }
    }
}
