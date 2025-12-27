<?php

use function Laravel\Folio\middleware;

middleware(['auth', 'verified']);

?>

<x-app-layout :isTool="true">
    <iframe src="https://picstome.com/calculator" width="100%" height="100%" style="border:none;" allowfullscreen></iframe>
</x-app-layout>
