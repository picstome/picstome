<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ __('Contract') }}</title>

    <style>
        body {
            background: #fff none;
            font-family: DejaVu Sans, 'sans-serif';
            font-size: 12px;
        }
    </style>
</head>
<body>

<div style="padding-top: 30px;">
    <h1 style="font-size: 28px; margin: 0">{{ $contract->title }}</h1>

    <p style="margin: 8px 0 0 0; font-size: 16px;">
        {{ $contract->description }}
    </p>

    <dl>
        <dt style="color: #555; font-weight: bold;">{{ __('Location') }}</dt>
        <dd>{{ $contract->location }}</dd>

        <dt style="color: #555; font-weight: bold;">{{ __('Shooting date') }}</dt>
        <dd>{{ $contract->formatted_shooting_date }}</dd>

        <dt style="color: #555; font-weight: bold;">{{ __('Contract terms') }}</dt>
        <dd>{!! $contract->formatted_markdown_body !!}</dd>
    </dl>

    <table style="margin-left: auto; margin-right: auto; margin-top: 30px;" width="100%">
        <tr>
            @foreach ($contract->signatures as $signature)
                @if($loop->index > 0 && $loop->index % 3 === 0)
                    </tr><tr>
                @endif
                <td width="33%">
                    <img src="{{ $signature->signature_image_url }}" style="max-width: 200px;" />

                    <dl>
                        <dt style="color: #555; font-weight: bold;">{{ __('Legal name') }}</dt>
                        <dd>{{ $signature->legal_name }}</dd>

                        <dt style="color: #555; font-weight: bold;">{{ __('Role') }}</dt>
                        <dd>{{ $signature->role }}</dd>

                        <dt style="color: #555; font-weight: bold;">{{ __('Birthday') }}</dt>
                        <dd>{{ $signature->formattedBirthday }}</dd>

                        <dt style="color: #555; font-weight: bold;">{{ __('Nationality') }}</dt>
                        <dd>{{ $signature->nationality }}</dd>

                        <dt style="color: #555; font-weight: bold;">{{ __('Document number') }}</dt>
                        <dd>{{ $signature->document_number }}</dd>

                        <dt style="color: #555; font-weight: bold;">{{ __('Email') }}</dt>
                        <dd>{{ $signature->email }}</dd>
                    </dl>
                </td>
            @endforeach
        </tr>
    </table>
</div>

</body>
</html>
