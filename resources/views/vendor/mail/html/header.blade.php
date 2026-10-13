{{--
    De kop van elke mail: het logo.

    **Hier stond de naam als tekst, en dat was een verdedigbare keuze die
    nu anders uitvalt.** De reden was dat mailprogramma's beelden
    blokkeren: dan staat er bovenaan een leeg vak waar de afzender hoort te
    staan. Maar dat is precies waar `alt` voor is -- blokkeert een
    programma het beeld, dan leest de ontvanger "@T IT Advies" in de kleur
    van het thema, net als eerst. En laadt hij wel, dan staat er het echte
    merk in plaats van een benadering met twee kleurtjes tekst.

    Drie dingen die daarbij horen:

    1. **PNG en geen WebP.** Outlook kan geen WebP, en dan valt de kop weg
       bij precies de ontvangers die hem het hardst nodig hebben. Er staat
       een `logo-merk.webp` in de map; gebruik die hier niet.

    2. **`logo-mail.png` en niet `logo-breed.png`.** Dat origineel is 2172
       pixels breed en 873 kB. Dit maatje is 380 breed -- het dubbele van
       waarop hij wordt getoond, dus scherp op elk scherm -- en 42 kB.

    3. **Een absolute URL.** Een mail heeft geen eigen domein om een pad
       vanaf te rekenen, dus `/images/...` zou nergens op uitkomen.
       `asset()` zet `APP_URL` ervoor.

    De breedte staat in het `width`-attribuut én in de stijl: Outlook kijkt
    naar het attribuut, de rest naar de stijl. Beide weglaten betekent dat
    hij op 380 pixels komt te staan.

    De kleur van de alt-tekst staat in het thema (`.header a`), want die
    hoort bij licht of donker en niet bij dit bestand. Zie
    docs/architecture/mail-en-queues.md.
--}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<img src="{{ asset('images/logo-mail.png') }}"
     alt="{{ config('app.name') }}"
     width="190"
     style="display: block; width: 190px; max-width: 190px; height: auto; border: 0; outline: none; text-decoration: none; margin: 0 auto;">
</a>
</td>
</tr>
