<?php

namespace AhgLibrary\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTradingPartnerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $partnerId = $this->route('partner')?->id;

        return [
            'edi_partner_code' => [
                'required', 'string', 'max:20',
                Rule::unique('library_trading_partner', 'edi_partner_code')->ignore($partnerId),
            ],
            'vendor_id'        => 'nullable|integer|exists:library_vendors,id',
            'edi_type'         => ['required', Rule::in(['EANCOM', 'X12', 'UN/EDIFACT', 'CUSTOM'])],
            'message_profile'  => ['required', Rule::in(['EANCOM_S93', 'EANCOM_S94', 'X12_850', 'CUSTOM'])],
            'endpoint_type'   => ['required', Rule::in(['SFTP', 'AS2', 'HTTP_HTTPS', 'EMAIL', 'MANUAL'])],
            'endpoint_config'  => ['required', 'array'],
            'endpoint_config.host'       => 'required_if:endpoint_type,SFTP|nullable|string|max:255',
            'endpoint_config.port'       => 'nullable|integer|min:1|max:65535',
            'endpoint_config.username'   => 'nullable|string|max:100',
            'endpoint_config.password'   => 'nullable|string|max:255',
            'endpoint_config.path'       => 'nullable|string|max:255',
            'endpoint_config.private_key'=> 'nullable|string',
            'endpoint_config.as2_url'     => 'required_if:endpoint_type,AS2|nullable|url|max:500',
            'endpoint_config.as2_receiver_id' => 'nullable|string|max:100',
            'endpoint_config.url'         => 'required_if:endpoint_type,HTTP_HTTPS|nullable|url|max:500',
            'endpoint_config.smtp_host'   => 'nullable|string|max:255',
            'endpoint_config.smtp_port'   => 'nullable|integer|min:1|max:65535',
            'endpoint_config.smtp_username' => 'nullable|string|max:100',
            'endpoint_config.smtp_password' => 'nullable|string|max:255',
            'endpoint_config.smtp_from'   => 'nullable|email|max:255',
            'endpoint_config.smtp_to'     => 'nullable|email|max:255',
            'outbound_directory' => 'nullable|string|max:255',
            'inbound_directory'  => 'nullable|string|max:255',
            'acknowledgement_required' => 'nullable',
            'test_mode'         => 'nullable',
            'is_active'         => 'nullable',
            'notes'             => 'nullable|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'edi_partner_code.unique' => 'This EDI partner code is already registered.',
            'endpoint_config.required' => 'Endpoint configuration is required.',
            'endpoint_config.host.required_if' => 'SFTP host is required.',
            'endpoint_config.as2_url.required_if' => 'AS2 URL is required.',
            'endpoint_config.url.required_if' => 'HTTP/HTTPS URL is required.',
        ];
    }
}
