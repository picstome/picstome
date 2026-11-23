@php
    /*
     * Mail theme (config `mail.markdown.theme`). Laravel renders it as a view
     * with the message data and inlines it into the HTML, so the colours and
     * font come from `$mailBrand` (App\Support\MailBranding), defaulting to
     * the Picstome brand. Based on Laravel's default theme.
     */
    $brand = $mailBrand ?? \App\Support\MailBranding::picstome();
@endphp
/* Base */

body,
body *:not(html):not(style):not(br):not(tr):not(code) {
    box-sizing: border-box;
    font-family: {!! $brand->fontStack() !!};
    position: relative;
}

body {
    -webkit-text-size-adjust: none;
    background-color: #ffffff;
    color: {{ $brand->text }};
    height: 100%;
    line-height: 1.4;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

p,
ul,
ol,
blockquote {
    line-height: 1.4;
    text-align: start;
}

a {
    color: {{ $brand->accent }};
}

a img {
    border: none;
}

/* Typography */

h1 {
    color: {{ $brand->heading }};
    font-size: 18px;
    font-weight: bold;
    margin-top: 0;
    text-align: start;
}

h2 {
    color: {{ $brand->heading }};
    font-size: 16px;
    font-weight: bold;
    margin-top: 0;
    text-align: start;
}

h3 {
    color: {{ $brand->heading }};
    font-size: 14px;
    font-weight: bold;
    margin-top: 0;
    text-align: left;
}

p {
    font-size: 16px;
    line-height: 1.5em;
    margin-top: 0;
    text-align: left;
}

p.sub {
    font-size: 12px;
}

img {
    max-width: 100%;
}

/* Layout */

.wrapper {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: {{ $brand->background }};
    margin: 0;
    padding: 0;
    width: 100%;
}

.content {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 0;
    padding: 0;
    width: 100%;
}

/* Header */

.header {
    padding: 25px 0;
    text-align: center;
}

.header a {
    color: {{ $brand->accent }};
    font-size: 19px;
    font-weight: bold;
    text-decoration: none;
}

/* Logo */

.logo {
    border: 0;
    height: auto;
    margin-top: 10px;
    max-height: 72px;
    max-width: 240px;
}

.brand-name {
    color: {{ $brand->accent }};
    font-size: 22px;
    font-weight: bold;
}

/* Body */

.body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: {{ $brand->background }};
    border-bottom: 1px solid {{ $brand->background }};
    border-top: 1px solid {{ $brand->background }};
    margin: 0;
    padding: 0;
    width: 100%;
}

.inner-body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    background-color: #ffffff;
    border-color: #e4e4e7;
    border-radius: 12px;
    border-width: 1px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
    margin: 0 auto;
    padding: 0;
    width: 570px;
}

.inner-body a {
    word-break: break-all;
}

/* Subcopy */

.subcopy {
    border-top: 1px solid #e4e4e7;
    margin-top: 25px;
    padding-top: 25px;
}

.subcopy p {
    font-size: 14px;
}

/* Footer */

.footer {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    margin: 0 auto;
    padding: 0;
    text-align: center;
    width: 570px;
}

.footer p {
    color: #a1a1aa;
    font-size: 12px;
    text-align: center;
}

.footer a {
    color: #a1a1aa;
    text-decoration: underline;
}

/* Tables */

.table table {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    width: 100%;
}

.table th {
    border-bottom: 1px solid #e4e4e7;
    margin: 0;
    padding-bottom: 8px;
}

.table td {
    color: {{ $brand->text }};
    font-size: 15px;
    line-height: 18px;
    margin: 0;
    padding: 10px 0;
}

.content-cell {
    max-width: 100vw;
    padding: 32px;
}

/* Buttons */

.action {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    padding: 0;
    text-align: center;
    width: 100%;
    float: unset;
}

.button {
    -webkit-text-size-adjust: none;
    border-radius: 8px;
    font-weight: 600;
    color: #fff;
    display: inline-block;
    overflow: hidden;
    text-decoration: none;
}

.button-blue,
.button-primary {
    background-color: {{ $brand->accent }};
    border-bottom: 10px solid {{ $brand->accent }};
    border-left: 22px solid {{ $brand->accent }};
    border-right: 22px solid {{ $brand->accent }};
    border-top: 10px solid {{ $brand->accent }};
}

.button-green,
.button-success {
    background-color: #16a34a;
    border-bottom: 8px solid #16a34a;
    border-left: 18px solid #16a34a;
    border-right: 18px solid #16a34a;
    border-top: 8px solid #16a34a;
}

.button-red,
.button-error {
    background-color: #dc2626;
    border-bottom: 8px solid #dc2626;
    border-left: 18px solid #dc2626;
    border-right: 18px solid #dc2626;
    border-top: 8px solid #dc2626;
}

/* Panels */

.panel {
    border-left: {{ $brand->accent }} solid 4px;
    margin: 21px 0;
}

.panel-content {
    background-color: {{ $brand->background }};
    color: {{ $brand->text }};
    padding: 16px;
}

.panel-content p {
    color: {{ $brand->text }};
}

.panel-item {
    padding: 0;
}

.panel-item p:last-of-type {
    margin-bottom: 0;
    padding-bottom: 0;
}

/* Utilities */

.break-all {
    word-break: break-all;
}
