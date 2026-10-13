{{--
    De sitemap. Zie App\Support\Page\Sitemap voor welke adressen erin gaan
    en waarom er geen hreflang, priority of changefreq in staat.

    `@verbatim` is hier niet nodig, maar let op de eerste regel: de
    XML-declaratie moet het állereerste zijn wat de browser ziet, zonder
    spatie of regel ervoor. Blade geeft dat alleen zo door.
--}}
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($adressen as $adres)
    <url>
        <loc>{{ $adres['loc'] }}</loc>
@if ($adres['lastmod'] !== null)
        <lastmod>{{ $adres['lastmod'] }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
