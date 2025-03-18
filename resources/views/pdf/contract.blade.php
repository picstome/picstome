<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contract</title>

    <style>
        body {
            background: #fff none;
            font-family: DejaVu Sans, 'sans-serif';
            font-size: 12px;
        }

        .container {
            padding-top: 30px;
        }

        .table th {
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            padding: 8px 8px 8px 0;
            vertical-align: bottom;
        }

        .table tr.row td {
            border-bottom: 1px solid #ddd;
        }

        .table td {
            padding: 8px 8px 8px 0;
            vertical-align: top;
        }

        .table th:last-child,
        .table td:last-child {
            padding-right: 0;
        }

        .sublte {
            color: #555;
        }

        .body p:first-child {
            margin-top: 0;
        }
    </style>
</head>
<body>

<div class="container">
    <table style="margin-left: auto; margin-right: auto;" width="100%">
        <tr valign="top">
            <td width="180">
                <span style="font-size: 28px;">
                    {{ $contract->title }}
                </span>

                <p>
                    {{ $contract->description }}
                </p>
            </td>
        </tr>
        <tr valign="top">
            <td width="25%">
                <strong class="sublte">{{ __('Location') }}</strong><br><br>

                <strong class="sublte">{{ __('Shooting date') }}</strong><br><br>

                <strong class="sublte">{{ __('Contract terms') }}</strong><br><br>
            </td>
            <td width="75%">
                {{ $contract->location }}<br><br>
                {{ $contract->formatted_shooting_date }}<br><br>
                <div class="body">{!! $contract->formatted_markdown_body !!}</div>
            </td>
        </tr>
        <tr>
            @foreach ($contract->signatures as $signature)
                @if($loop->index > 0 && $loop->index % 3 === 0)
                    </tr><tr>
                @endif
                <td width="33%">
                    <table class="table" border="0">
                        <tr class="row">
                            <td style="font-size: 14px;">
                                <img src="{{ $signature->signature_image_url }}" style="max-width: 200px;" />

                                <div style="text-align: center;">{{ $signature->legal_name }}</div>
                            </td>
                        </tr>
                        <tr class="row">
                            <td>
                                <strong class="sublte">{{ __('Role') }}</strong>: {{ $signature->role }}
                            </td>
                        </tr>
                        <tr class="row">
                            <td>
                                <strong class="sublte">{{ __('Birthday') }}</strong>: {{ $signature->formattedBirthday }}
                            </td>
                        </tr>
                        <tr class="row">
                            <td>
                                <strong class="sublte">{{ __('Nationality') }}</strong>: {{ $signature->nationality }}
                            </td>
                        </tr>
                        <tr class="row">
                            <td>
                                <strong class="sublte">{{ __('Document number') }}</strong>: {{ $signature->document_number }}
                            </td>
                        </tr>
                        <tr class="row">
                            <td>
                                <strong class="sublte">{{ __('Email') }}</strong>: {{ $signature->email }}
                            </td>
                        </tr>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>
</div>

</body>
</html>
