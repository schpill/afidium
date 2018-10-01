<?php
define('DS', DIRECTORY_SEPARATOR);

function dd(...$args)
{
    var_dump(...$args);
    exit;
}

class Bonotel
{
    const CACHE_TTL = 900;
    const DIR_DATA = __DIR__ . DS . 'data';
    const DIR_JSON = __DIR__ . DS . 'json';
    const URL_ENDPOINT = 'http://api.bonotel.com/index.cfm/user/voyagrs_xml/action/hotel';

    /**
     * @return void
     */
    public static function start(): void {
        static::getData();
        static::parse();
        die('Import terminé');
    }

    /**
     * @return void
     */
    private static function getData(): void {
        $file = static::DIR_DATA . DS . 'data.xml';

        if (!file_exists($file)) {
            file_put_contents($file, file_get_contents(static::URL_ENDPOINT));
        } else  {
            /** cached for 15 minutes */
            if (time() - filemtime($file) > static::CACHE_TTL) {
                unlink($file);
                static::getData();
            }
        }
    }

    /**
     * @return void
     */
    private static function parse(): void {
        $hotels = simplexml_load_file(static::DIR_DATA . DS . 'data.xml');

        foreach ($hotels as $hotel) {
            $code = (string) $hotel->hotelCode;

            $row = [];

            $row['latitude'] = (float) $hotel->latitude;
            $row['longitude'] = (float) $hotel->longitude;

            $countryCode = (string) $hotel->countryCode;
            $language = strtoupper(locale_get_primary_language(
                static::getLocaleFromCountryCode($countryCode)
            ));

            $row['language'] = $language;
            $row['rating_level'] = (float) $hotel->starRating;

            $recreation = (string) $hotel->recreation;
            $facilities = (string) $hotel->facilities;
            $description = (string) $hotel->description;

            $row['swimmingpool'] = static::hasFacilityOrHasRecreation($recreation, $facilities, 'swimming pool');
            $row['parking'] = static::hasFacilityOrHasRecreation($recreation, $facilities, 'parking');
            $row['fitness'] = static::hasFacilityOrHasRecreation($recreation, $facilities, 'fitness');
            $row['golf'] = static::hasFacilityOrHasRecreation($recreation, $facilities, 'golf');
            $row['seaside'] = static::hasFacilityOrHasRecreation($recreation, $facilities, 'seaside');
            $row['spa'] = static::hasFacilityOrHasRecreation($recreation, $facilities, 'spa');
            $row['charm'] = static::hasFacilityOrHasRecreation($recreation, $facilities, 'charm');
            $row['ecotourism'] = static::hasFacilityOrHasRecreation($recreation, $facilities, 'eco tour');
            $row['exceptional'] = static::contains($description, 'exceptional');
            $row['family_friendly'] = static::contains($description, 'family friendly');
            $row['pmr'] = static::contains($description, 'handicap')
                || static::hasFacilityOrHasRecreation($recreation, $facilities, 'handicap');
            $row['preferred'] = static::contains($description, 'preferred')
                || static::hasFacilityOrHasRecreation($recreation, $facilities, 'preferred');
            $row['wedding'] = static::contains($description, 'wedding')
                || static::hasFacilityOrHasRecreation($recreation, $facilities, 'wedding');

            $row['distribution']['BONOTEL'] = $code;

            $row['introduction_text']['language'] = $language;
            $row['introduction_text']['type_code'] = 'Description';
            $row['introduction_text']['title'] = 'Description';
            $row['introduction_text']['text'] = $description;

            $images = (array) $hotel->images;
            $image = current($images['image']);

            $imageInfos = static::getImageInfos($image);

            $row['introduction_media']['weight'] = ['value' => end($imageInfos), 'unit' => 'Byte'];
            $row['introduction_media']['size'] = ['width' => $imageInfos[0], 'height' => $imageInfos[1], 'unit' => 'px'];
            $row['introduction_media']['url'] = $image;

            static::makeJson($code, $row);
        }
    }

    /**
     * @return void
     */
    private static function makeJson(string $_code, array $_row): void {
        $file = static::DIR_JSON . DS . 'HS_BNO_H_' . $_code . '.json';

        if (file_exists($file)) {
            unlink($file);
        }

        file_put_contents($file, json_encode($_row, JSON_PRETTY_PRINT));
    }

    /**
     * @param string $_url
     * @return array|bool
     */
    private static function getImageInfos(string $_url) {
        $parts = explode('/', $_url);
        $name = end($parts);
        $file = static::DIR_DATA . DS . $name;
        file_put_contents($file, file_get_contents($_url));

        $size = filesize($file);
        $imageSize = getImageSize($file);
        $imageSize[] = $size;

        unlink($file);

        return $imageSize;
    }

    /**
     * @param string $_recreation
     * @param string $_facilities
     * @param string $_scope
     * @return bool
     */
    private static function hasFacilityOrHasRecreation(string $_recreation, string $_facilities, string $_scope): bool {
        return static::contains($_recreation, $_scope) || static::contains($_facilities, $_scope);
    }

    /**
     * @param string $string
     * @param string $search
     * @return bool
     */
    private static function contains(string $_string, string $_search): bool {
        return false !== strstr($_string, $_search);
    }

    /**
     * @param string $_code
     * @return string
     */
    private static function getLocaleFromCountryCode(string $_code): string {
        # http://wiki.openstreetmap.org/wiki/Nominatim/Country_Codes
        $arr = [
            'ad' => 'ca',
            'ae' => 'ar',
            'af' => 'fa,ps',
            'ag' => 'en',
            'ai' => 'en',
            'al' => 'sq',
            'am' => 'hy',
            'an' => 'nl,en',
            'ao' => 'pt',
            'aq' => 'en',
            'ar' => 'es',
            'as' => 'en,sm',
            'at' => 'de',
            'au' => 'en',
            'aw' => 'nl,pap',
            'ax' => 'sv',
            'az' => 'az',
            'ba' => 'bs,hr,sr',
            'bb' => 'en',
            'bd' => 'bn',
            'be' => 'nl,fr,de',
            'bf' => 'fr',
            'bg' => 'bg',
            'bh' => 'ar',
            'bi' => 'fr',
            'bj' => 'fr',
            'bl' => 'fr',
            'bm' => 'en',
            'bn' => 'ms',
            'bo' => 'es,qu,ay',
            'br' => 'pt',
            'bq' => 'nl,en',
            'bs' => 'en',
            'bt' => 'dz',
            'bv' => 'no',
            'bw' => 'en,tn',
            'by' => 'be,ru',
            'bz' => 'en',
            'ca' => 'en,fr',
            'cc' => 'en',
            'cd' => 'fr',
            'cf' => 'fr',
            'cg' => 'fr',
            'ch' => 'de,fr,it,rm',
            'ci' => 'fr',
            'ck' => 'en,rar',
            'cl' => 'es',
            'cm' => 'fr,en',
            'cn' => 'zh',
            'co' => 'es',
            'cr' => 'es',
            'cu' => 'es',
            'cv' => 'pt',
            'cw' => 'nl',
            'cx' => 'en',
            'cy' => 'el,tr',
            'cz' => 'cs',
            'de' => 'de',
            'dj' => 'fr,ar,so',
            'dk' => 'da',
            'dm' => 'en',
            'do' => 'es',
            'dz' => 'ar',
            'ec' => 'es',
            'ee' => 'et',
            'eg' => 'ar',
            'eh' => 'ar,es,fr',
            'er' => 'ti,ar,en',
            'es' => 'es,ast,ca,eu,gl',
            'et' => 'am,om',
            'fi' => 'fi,sv,se',
            'fj' => 'en',
            'fk' => 'en',
            'fm' => 'en',
            'fo' => 'fo',
            'fr' => 'fr',
            'ga' => 'fr',
            'gb' => 'en,ga,cy,gd,kw',
            'gd' => 'en',
            'ge' => 'ka',
            'gf' => 'fr',
            'gg' => 'en',
            'gh' => 'en',
            'gi' => 'en',
            'gl' => 'kl,da',
            'gm' => 'en',
            'gn' => 'fr',
            'gp' => 'fr',
            'gq' => 'es,fr,pt',
            'gr' => 'el',
            'gs' => 'en',
            'gt' => 'es',
            'gu' => 'en,ch',
            'gw' => 'pt',
            'gy' => 'en',
            'hk' => 'zh,en',
            'hm' => 'en',
            'hn' => 'es',
            'hr' => 'hr',
            'ht' => 'fr,ht',
            'hu' => 'hu',
            'id' => 'id',
            'ie' => 'en,ga',
            'il' => 'he',
            'im' => 'en',
            'in' => 'hi,en',
            'io' => 'en',
            'iq' => 'ar,ku',
            'ir' => 'fa',
            'is' => 'is',
            'it' => 'it,de,fr',
            'je' => 'en',
            'jm' => 'en',
            'jo' => 'ar',
            'jp' => 'ja',
            'ke' => 'sw,en',
            'kg' => 'ky,ru',
            'kh' => 'km',
            'ki' => 'en',
            'km' => 'ar,fr',
            'kn' => 'en',
            'kp' => 'ko',
            'kr' => 'ko,en',
            'kw' => 'ar',
            'ky' => 'en',
            'kz' => 'kk,ru',
            'la' => 'lo',
            'lb' => 'ar,fr',
            'lc' => 'en',
            'li' => 'de',
            'lk' => 'si,ta',
            'lr' => 'en',
            'ls' => 'en,st',
            'lt' => 'lt',
            'lu' => 'lb,fr,de',
            'lv' => 'lv',
            'ly' => 'ar',
            'ma' => 'ar',
            'mc' => 'fr',
            'md' => 'ru,uk,ro',
            'me' => 'srp,sq,bs,hr,sr',
            'mf' => 'fr',
            'mg' => 'mg,fr',
            'mh' => 'en,mh',
            'mk' => 'mk',
            'ml' => 'fr',
            'mm' => 'my',
            'mn' => 'mn',
            'mo' => 'zh,en,pt',
            'mp' => 'ch',
            'mq' => 'fr',
            'mr' => 'ar,fr',
            'ms' => 'en',
            'mt' => 'mt,en',
            'mu' => 'mfe,fr,en',
            'mv' => 'dv',
            'mw' => 'en,ny',
            'mx' => 'es',
            'my' => 'ms,zh,en',
            'mz' => 'pt',
            'na' => 'en,sf,de',
            'nc' => 'fr',
            'ne' => 'fr',
            'nf' => 'en,pih',
            'ng' => 'en',
            'ni' => 'es',
            'nl' => 'nl',
            'no' => 'nb,nn,no,se',
            'np' => 'ne',
            'nr' => 'na,en',
            'nu' => 'niu,en',
            'nz' => 'en,mi',
            'om' => 'ar',
            'pa' => 'es',
            'pe' => 'es',
            'pf' => 'fr',
            'pg' => 'en,tpi,ho',
            'ph' => 'en,tl',
            'pk' => 'en,ur',
            'pl' => 'pl',
            'pm' => 'fr',
            'pn' => 'en,pih',
            'pr' => 'es,en',
            'ps' => 'ar,he',
            'pt' => 'pt',
            'pw' => 'en,pau,ja,sov,tox',
            'py' => 'es,gn',
            'qa' => 'ar',
            're' => 'fr',
            'ro' => 'ro',
            'rs' => 'sr',
            'ru' => 'ru',
            'rw' => 'rw,fr,en',
            'sa' => 'ar',
            'sb' => 'en',
            'sc' => 'fr,en,crs',
            'sd' => 'ar,en',
            'se' => 'sv',
            'sg' => 'en,ms,zh,ta',
            'sh' => 'en',
            'si' => 'sl',
            'sj' => 'no',
            'sk' => 'sk',
            'sl' => 'en',
            'sm' => 'it',
            'sn' => 'fr',
            'so' => 'so,ar',
            'sr' => 'nl',
            'st' => 'pt',
            'ss' => 'en',
            'sv' => 'es',
            'sx' => 'nl,en',
            'sy' => 'ar',
            'sz' => 'en,ss',
            'tc' => 'en',
            'td' => 'fr,ar',
            'tf' => 'fr',
            'tg' => 'fr',
            'th' => 'th',
            'tj' => 'tg,ru',
            'tk' => 'tkl,en,sm',
            'tl' => 'pt,tet',
            'tm' => 'tk',
            'tn' => 'ar',
            'to' => 'en',
            'tr' => 'tr',
            'tt' => 'en',
            'tv' => 'en',
            'tw' => 'zh',
            'tz' => 'sw,en',
            'ua' => 'uk',
            'ug' => 'en,sw',
            'um' => 'en',
            'us' => 'en,es',
            'uy' => 'es',
            'uz' => 'uz,kaa',
            'va' => 'it',
            'vc' => 'en',
            've' => 'es',
            'vg' => 'en',
            'vi' => 'en',
            'vn' => 'vi',
            'vu' => 'bi,en,fr',
            'wf' => 'fr',
            'ws' => 'sm,en',
            'ye' => 'ar',
            'yt' => 'fr',
            'za' => 'zu,xh,af,st,tn,en',
            'zm' => 'en',
            'zw' => 'en,sn,nd'
    ];

        $code = strtolower($_code);

        if ($code === 'eu') {
            return 'en_GB';
        } elseif ($code === 'ap') { # Asia Pacific
            return 'en_US';
        } elseif ($code === 'cs') {
            return 'sr_RS';
        }

        if ($code === 'uk') {
            $code = 'gb';
        }

        if (array_key_exists($code, $arr)) {
            if (strpos($arr[$code], ',') !== false) {
                $new = explode(',', $arr[$code]);
                $loc = [];

                foreach ($new as $key => $val) {
                    $loc[] = $val . '_' . strtoupper($code);
                }

                return implode(',', $loc);
            } else {
                return $arr[$code] . '_' . strtoupper($code);
            }
        }
        return 'en_US';
    }
}

Bonotel::start();
