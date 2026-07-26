<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'NCAT FMD Order')</title>
    {{--
        The order forms are reproduced from forms_reference/Purchase Order.png
        and Repair Order.png rather than styled in the internal voucher house
        style: these go out to vendors, so the layout on their desk has to match
        the paper the department has always sent. Serif type, hairline table
        rules, and a boxed title, all as printed.
    --}}
    <style>
        @page { margin: 28px 34px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Serif, serif; font-size: 10.5px; color: #000; margin: 0; }
        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .crest { height: 44px; }
        .college { font-family: DejaVu Sans, sans-serif; font-size: 17px; font-weight: bold; letter-spacing: -.2px; }
        .addr { font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.5; }
        .contact-block { font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.5; text-align: center; }
        .contact-block a, .link { color: #0000cc; text-decoration: underline; }

        /* Boxed form title: a thick outer rule with an inner box, as printed. */
        .title-wrap { text-align: center; margin: 16px 0 10px; }
        .title-outer { display: inline-block; border: 1px solid #000; padding: 14px 40px; }
        .title-inner { border: 2px solid #000; padding: 5px 22px; font-size: 19px; letter-spacing: .5px; }

        .refline { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .refline .ref { font-weight: bold; font-size: 12px; letter-spacing: .5px; }
        .refline .date { text-align: right; font-size: 12px; }

        .vendor { font-weight: bold; font-size: 12px; line-height: 1.45; margin-bottom: 8px; }
        .vendor td { vertical-align: top; }
        .actype { font-weight: bold; font-size: 11.5px; margin: 6px 0 4px; }

        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #000; padding: 4px 6px; font-size: 10.5px; }
        table.lines th { font-weight: bold; text-align: center; }
        table.lines td { vertical-align: top; }
        .c { text-align: center; }

        /* NOTE box: double rule on the sample, inset from both margins. */
        .note-wrap { margin: 14px 0 10px; padding: 0 90px 0 110px; }
        .note { border: 3px double #000; padding: 6px 9px; font-size: 10.5px; line-height: 1.45; text-align: justify; }
        .note .lbl { font-family: DejaVu Sans, sans-serif; font-weight: bold; display: block; }

        .contacts { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .contacts td { vertical-align: top; font-family: DejaVu Sans, sans-serif; }
        .contacts .heading { font-size: 12px; font-weight: bold; }
        .contacts .who { font-size: 11px; font-weight: bold; }

        .priority { width: 100%; border-collapse: collapse; margin-top: 18px; font-family: DejaVu Sans, sans-serif; }
        .priority td { font-size: 11.5px; font-weight: bold; }
        .box { display: inline-block; width: 15px; height: 15px; border: 1px solid #000; text-align: center; line-height: 14px; font-size: 12px; margin-right: 7px; }

        .signoff { margin-top: 26px; font-family: DejaVu Sans, sans-serif; font-size: 12px; font-weight: bold; }
        .signoff .dots { font-weight: normal; letter-spacing: 1px; }
        .signoff .role { margin-left: 78px; }

        .demo-wm { position: absolute; top: 42%; left: 8%; z-index: 0; transform: rotate(-45deg); color: #e5e7eb; font-family: DejaVu Sans, sans-serif; font-size: 120px; font-weight: bold; letter-spacing: 12px; white-space: nowrap; }
        .draft-note { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; text-align: center; margin-top: 10px; }
    </style>
</head>
<body>
    @if(app(\App\Services\Demo\DemoMode::class)->isActive())
        <div class="demo-wm">DEMO</div>
    @endif

    <table class="head">
        <tr>
            <td style="width: 54px;"><img src="{{ $crest }}" class="crest" alt="NCAT"></td>
            <td>
                <div class="college">NIGERIAN COLLEGE OF AVIATION TECHNOLOGY, ZARIA</div>
                <table style="width: 100%; border-collapse: collapse; margin-top: 4px;">
                    <tr>
                        <td class="addr" style="width: 38%;">
                            {{ $settings['address_line_1'] }}<br>
                            {{ $settings['address_line_2'] }}
                        </td>
                        <td class="contact-block">
                            E-mail: <span class="link">{{ $settings['email_line_1'] }}</span><br>
                            <span class="link">{{ $settings['email_line_2'] }}</span><br>
                            Website: <span class="link">{{ $settings['website'] }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="title-wrap">
        <span class="title-outer"><span class="title-inner">@yield('form-title')</span></span>
    </div>

    <table class="refline">
        <tr>
            <td class="ref">{{ $reference }}</td>
            <td class="date">{!! $orderDate !!}</td>
        </tr>
    </table>

    @yield('body')

    <div class="note-wrap">
        <div class="note">
            <span class="lbl">NOTE:</span>
            {{ $note }}
        </div>
    </div>

    <table class="contacts">
        <tr>
            <td style="width: 55%;" class="heading">@yield('contacts-heading', 'NCAT CONTACT:')</td>
            <td>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 26px; vertical-align: top;" class="who">I.</td>
                        <td>
                            <span class="who">{{ $settings['contact_1_name'] }}</span><br>
                            <span class="link" style="font-size: 10px;">{{ $settings['contact_1_email'] }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 26px; vertical-align: top;" class="who">II.</td>
                        <td>
                            <span class="who">{{ $settings['contact_2_name'] }}</span><br>
                            <span class="link" style="font-size: 10px;">{{ $settings['contact_2_email'] }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="priority">
        <tr>
            <td style="width: 33%;"><span class="box">{{ $priority === 'aog' ? '✓' : '' }}</span>A.O.G</td>
            <td style="width: 34%;"><span class="box">{{ $priority === 'very_urgent' ? '✓' : '' }}</span>Very Urgent</td>
            <td><span class="box">{{ $priority === 'for_inventory' ? '✓' : '' }}</span>For inventory</td>
        </tr>
    </table>

    <div class="signoff">
        Prepared by: <span class="dots">.................................................</span>
        <div class="role">{{ $preparedBy }}</div>
    </div>

    @if($isDraft)
        <div class="draft-note">
            DRAFT. This order has no reference yet and has not been issued to the vendor.
        </div>
    @endif
</body>
</html>
