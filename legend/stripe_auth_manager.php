<?php

require_once 'config.php';

/**
 * Manages Stripe Auth site rotation and configuration.
 */
class StripeAuthSiteManager
{
    private const STATE_FILE = __DIR__ . '/data/stripe_auth_state.json';

    private const CONFIG_SITES = 'stripe_auth_sites';
    private const CONFIG_REQUESTS_PER_SITE = 'stripe_auth_requests_per_site';
    private const CONFIG_CREDIT_COST = 'stripe_auth_credit_cost';
    private const CONFIG_MANUAL_SITE = 'stripe_auth_manual_site';
    private const CONFIG_NOTIFY_LIVE = 'stripe_auth_notify_live';
    private const CONFIG_NOTIFY_DEAD = 'stripe_auth_notify_dead';
    private const CONFIG_NOTIFY_ERROR = 'stripe_auth_notify_error';

    /**
     * Get configured Stripe auth sites. If none configured, seed defaults.
     */
    public static function getSites(): array
    {
        $sites = SiteConfig::get(self::CONFIG_SITES, null);

        if (!is_array($sites) || empty($sites)) {
            $sites = self::getDefaultSites();
            self::setSites($sites);
        }

        return $sites;
    }

    /**
     * Replace current site list with provided list.
     */
    public static function setSites(array $sites): bool
    {
        $clean = [];

        foreach ($sites as $site) {
            $sanitized = self::sanitizeSite($site);
            if ($sanitized) {
                $clean[$sanitized] = $sanitized; // Prevent duplicates
            }
        }

        $finalSites = array_values($clean);

        if (SiteConfig::save([self::CONFIG_SITES => $finalSites])) {
            self::resetState();
            return true;
        }

        return false;
    }

    /**
     * Append a site to the list.
     */
    public static function addSite(string $site): bool
    {
        $sanitized = self::sanitizeSite($site);
        if (!$sanitized) {
            return false;
        }

        $sites = self::getSites();
        if (!in_array($sanitized, $sites, true)) {
            $sites[] = $sanitized;
            return self::setSites($sites);
        }

        return true;
    }

    /**
     * Remove a site from the list.
     */
    public static function removeSite(string $site): bool
    {
        $sanitized = self::sanitizeSite($site);
        if (!$sanitized) {
            return false;
        }

        $sites = array_filter(
            self::getSites(),
            static fn($existing) => $existing !== $sanitized
        );

        return self::setSites(array_values($sites));
    }

    /**
     * Get number of requests before rotating to next site.
     */
    public static function getRequestsPerSite(): int
    {
        $value = (int) SiteConfig::get(self::CONFIG_REQUESTS_PER_SITE, 20);
        return max(1, $value);
    }

    /**
     * Update rotation threshold.
     */
    public static function setRequestsPerSite(int $count): bool
    {
        $count = max(1, $count);
        return SiteConfig::save([self::CONFIG_REQUESTS_PER_SITE => $count]);
    }

    /**
     * Get credit cost per Stripe auth check.
     */
    public static function getCreditCost(): int
    {
        $value = (int) SiteConfig::get(self::CONFIG_CREDIT_COST, 1);
        return max(1, $value);
    }

    /**
     * Update credit cost per check.
     */
    public static function setCreditCost(int $cost): bool
    {
        $cost = max(1, $cost);
        return SiteConfig::save([self::CONFIG_CREDIT_COST => $cost]);
    }

    /**
     * Get manual override site (if configured).
     */
    public static function getManualSite(): ?string
    {
        $site = SiteConfig::get(self::CONFIG_MANUAL_SITE, null);
        $sanitized = self::sanitizeSite($site);
        return $sanitized ?: null;
    }

    /**
     * Set manual override site (null clears override).
     */
    public static function setManualSite(?string $site): bool
    {
        $sanitized = self::sanitizeSite($site);
        if ($sanitized) {
            self::resetState();
            return SiteConfig::save([self::CONFIG_MANUAL_SITE => $sanitized]);
        }

        self::resetState();
        return SiteConfig::save([self::CONFIG_MANUAL_SITE => null]);
    }

    /**
     * Determine if notifications should be sent for a status type.
     */
    public static function shouldNotify(string $statusType): bool
    {
        $statusType = strtoupper($statusType);

        return match ($statusType) {
            'LIVE', 'APPROVED', 'SUCCESS' =>
                (bool) SiteConfig::get(self::CONFIG_NOTIFY_LIVE, true),
            'DEAD', 'DECLINED', 'FAILED' =>
                (bool) SiteConfig::get(self::CONFIG_NOTIFY_DEAD, true),
            default =>
                (bool) SiteConfig::get(self::CONFIG_NOTIFY_ERROR, false),
        };
    }

    /**
     * Save notification preference settings.
     */
    public static function setNotificationPreferences(bool $live, bool $dead, bool $error): bool
    {
        return SiteConfig::save([
            self::CONFIG_NOTIFY_LIVE => $live,
            self::CONFIG_NOTIFY_DEAD => $dead,
            self::CONFIG_NOTIFY_ERROR => $error,
        ]);
    }

    /**
     * Fetch notification preferences.
     */
    public static function getNotificationPreferences(): array
    {
        return [
            'live' => (bool) SiteConfig::get(self::CONFIG_NOTIFY_LIVE, true),
            'dead' => (bool) SiteConfig::get(self::CONFIG_NOTIFY_DEAD, true),
            'error' => (bool) SiteConfig::get(self::CONFIG_NOTIFY_ERROR, false),
        ];
    }

    /**
     * Retrieve next site in rotation, respecting manual override.
     *
     * @throws RuntimeException if no sites configured.
     */
    public static function getNextSite(): string
    {
        $manual = self::getManualSite();
        if ($manual) {
            return $manual;
        }

        $sites = self::getSites();
        if (empty($sites)) {
            throw new RuntimeException('Stripe Auth sites list is empty. Configure sites in admin panel.');
        }

        $requestsPerSite = self::getRequestsPerSite();
        $state = self::loadState();

        $index = (int) ($state['index'] ?? 0);
        $count = (int) ($state['count'] ?? 0);
        $totalSites = count($sites);

        if ($index >= $totalSites || $index < 0) {
            $index = 0;
            $count = 0;
        }

        if ($count >= $requestsPerSite) {
            $index = ($index + 1) % $totalSites;
            $count = 0;
        }

        $site = $sites[$index];
        $count++;

        self::saveState([
            'index' => $index,
            'count' => $count,
            'updated_at' => time()
        ]);

        return $site;
    }

    /**
     * Reset rotation state.
     */
    public static function resetState(): void
    {
        $path = self::STATE_FILE;
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Sanitize site string.
     */
    public static function sanitizeSite(?string $site): ?string
    {
        if ($site === null) {
            return null;
        }

        $site = trim($site);
        $site = trim($site, "\"' \t\n\r");

        if ($site === '') {
            return null;
        }

        // Remove scheme if provided
        $site = preg_replace('#^https?://#i', '', $site);
        $site = preg_replace('#^//+#', '', $site);

        // Remove trailing slashes
        $site = rtrim($site, "/ \t\n\r");

        return strtolower($site);
    }

    /**
     * Load rotation state from disk.
     */
    private static function loadState(): array
    {
        $path = self::STATE_FILE;

        if (!file_exists($path)) {
            return ['index' => 0, 'count' => 0];
        }

        $fp = @fopen($path, 'r');
        if (!$fp) {
            return ['index' => 0, 'count' => 0];
        }

        try {
            if (flock($fp, LOCK_SH)) {
                $contents = stream_get_contents($fp);
                flock($fp, LOCK_UN);
            } else {
                $contents = '';
            }
        } finally {
            fclose($fp);
        }

        $data = json_decode($contents ?: '', true);
        if (!is_array($data)) {
            return ['index' => 0, 'count' => 0];
        }

        return [
            'index' => (int) ($data['index'] ?? 0),
            'count' => (int) ($data['count'] ?? 0),
            'updated_at' => $data['updated_at'] ?? null
        ];
    }

    /**
     * Persist rotation state to disk.
     */
    private static function saveState(array $state): void
    {
        $path = self::STATE_FILE;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $fp = fopen($path, 'c+');
        if (!$fp) {
            throw new RuntimeException('Unable to write Stripe auth rotation state.');
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException('Could not acquire lock for Stripe auth state file.');
            }

            $payload = json_encode(
                [
                    'index' => (int) ($state['index'] ?? 0),
                    'count' => (int) ($state['count'] ?? 0),
                    'updated_at' => $state['updated_at'] ?? time()
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );

            if ($payload === false) {
                throw new RuntimeException('Failed to encode Stripe auth state.');
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $payload);
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }

    /**
     * Retrieve default Stripe auth site list.
     */
    private static function getDefaultSites(): array
    {
        $rawList = <<<EOT
alternativesentiments.co.uk
alphaomegastores.com
attitudedanceleeds.co.uk
ankicolemandesigns.com
aeoebookstore.net
allabout-gymnastics.co.uk
balkanbred.com
biothik.com.au
anchormusic.com
charleshobson.co.uk
annfashion.co.uk
borabeads.co.uk
banxsysupplies.co.uk
bhfc.co.uk
browbandsupplies.com
automationguarding.com
beeyoupr.com
bcdetails.co.uk
captainbobsim.com
christhuntproductions.com
crystalcanvas.us
chocolatemusings.com
bollywoodbrisbane.com.au
collinets.com
dejachaniah.com
curiousbrewery.com
dahlonegageneralstore.com
cookwareobsession.com.au
dotslines.ca
dleighdesigns.com
diychris.com
dronein.com
dixondalefarms.com
custompedalboards.co.uk
flowersbyalina.co.uk
filelabelexpress.com
championtreats.com
factory.bumpey.net
fragrancescentsandmore.com
emsbakehouse.com
fingershield.co.uk
envybeautylablaser.com
giftitup.ca
glidex.ca
grovebooks.co.uk
gentiluomo.ca
filmfiltertags.com
getvie.com
hochwald.us
glenboles.ca
granitecliffstudio.com
hopkinsoncycles.co.uk
glittzandglue.com
hewittlearning.org
isolazen.com
illumadesigns.co.uk
irishgirlswreaths.com
jeffcmusic.com
kunitzshoes.ca
extremedealsoftheday.com
gripperlsd.com
imagineacoustics.ca
flyingcoffee.co.uk
jukeboxrecords.org
jovs.co.uk
kasadesigns.com
herbaloasisbodycare.com
laelle.co.uk
jenncampusauthor.com
empcho.com
kazcraftedbits.co.uk
huskaloo.com
ironshop.ca
landnhairproducts.co.uk
lismorespares.com.au
leafandpetal.co.uk
luxeroses.com.au
gulfcoastoutfitters.com
marbleslabhouston.com
homefurnishingsrus.co.uk
ljdono.co.uk
kaladicoffee.com
melodylane.com
kimmett.ca
littleblossomgifts.com.au
melhairandstyle.com
jewellerytailor.com
mcontemp.com
lasercap.com
littlegemsonline.co.uk
kathycreates.com
medicamins.com
melbourneanimalphysiotherapy.com.au
midsotamfg.promotionalresourcesinc.net
livingwholey.com
luxesound.ca
megheathdogleads.co.uk
le-tech.co.uk
lighting4home.com
monsterrodholders.com
mondovitality.com
melbiggsmusic.co.uk
medcakes.co.uk
orcahygiene.com
pendlevalleyworkshop.uk
nurge.net
origenfoods.ca
patchcollective.uk
magickalmystic.com
nationalhomesupplies.com
music-rooms.com
officechairman.co.uk
noteslondon.co.uk
orchia.ca
needlesstoys.com
onlinefixings.co.uk
norwayomega.com
regalstamps.co.uk
pnwcharm.com
hearthflair.com
pranaworld.net
moleculesports.com.au
obsidien.com
pachaoflondon.com
prettyprezzies.com
royalpadel.co.uk
puredeadgallus.co.uk
piecesof8.org
rcworxs.co.uk
rudyandlou.co.uk
redbluechair.com
shop.drawinghouse.ca
scenttocoventry.com
shirleyveaterdesigns.co.uk
shop.machinemetrics.com
steeleselectrical.co.uk
shop.sockhodler.com
shop.thetravelbible.com
solomotorsports.com
originaleditions.com.au
simpledailyjoystore.com
springharveststore.com
sweepofsand.com
starsandheroesmalta.com
stylishsimone.com
themodeltrainshop.co.uk
thegpsgirl.com
sledmanuals.com
supplies4you.co.uk
toi.maorilandfilm.co
thespeckyseamstress.com
tidewater3d.co.uk
ultimacnc.store
thestitcherhood.com
twistedkombucha.co.uk
vertigomotors.co.uk
undergroundreptiles.com
unclesupply.com
travel-esim.net
userfriendlyresources.co
thehaircompanyni.com
vintageanalogdigitalmedia.com
studio4signs.com
toolboxsessions.com
prindustrial.co.uk
vintage-replay.com
victorychick.com
umug.uk
welovelaptops.net
wickedtransfers.com
treatmetreasureme.com.au
valuondemand.com
vitos.ca
wisconsinmeadows.com
wapitiarcherypoc.com
www.artbydakotadean.com
witchwayaustralia.com
woodpanelwalls.com
vinyltavern.com
whitefoxcandles.com
www.babycastingco.co.uk
www.craftmetropolis.co.uk
wilderbydesign.co.uk
www.baronycountryfoods.co.uk
www.constar.com.au
www.ammanatur.us
www.canime.co.uk
www.alpacaemporium.co.uk
www.craiglonggallery.co.uk
www.aryabikini.com
www.crystalsandgifts.co.uk
www.giftedbostonspa.co.uk
unclejohnsoutfitters.com
www.cartertonsocialfc.co.uk
www.doubleostyles.com
www.autotrad.com.au
www.drstoystore.com
www.farrers.co.uk
www.gt-turbo-spares.co.uk
www.hitek-ltd.co.uk
www.festaforesta.com
www.lottienottie.com
www.idealsuk.co.uk
www.kingdomliquor.com
www.erushmo.com
www.iamats.com
www.indemax.com
www.hooklineandtackle.co.uk
www.k1ever.ca
www.elmaliquor.com
www.masterslaundry.com.au
www.lighting-geek.com
www.hoseshop.net
www.goprintpr.com
www.mionlineshop.com.au
www.loungely.com.au
www.handlesandmore.com.au
www.aussiecalendars.com
www.motortechnology.uk
www.glasshousestore.com
www.internationalwardrobe.com
www.divinglocker.ca
www.discountdelights.co.uk
www.osmosis.com
www.parshop.co.uk
www.refillreuserenew.co.uk
www.schoolofskate.co.uk
www.spiritcrystals.com
www.wilton-patisserie.co.uk
www.tuning-database.co.uk
www.pyrosales.com.au
www.rangersremorphed.com
www.teachergameroom.com
www.noboundariesjc.com
www.winetrove.co.uk
yorubalessons.com
www.packhorse.co.uk
www.pinkland.co
www.theoldladysattic.com
ynhoia.com
winejunction.net
ximi-bexleyheath.co.uk
www.thepurplepeddler.com
EOT;

        $lines = preg_split('/\r?\n/', trim($rawList));
        $sites = [];

        foreach ($lines as $line) {
            $sanitized = self::sanitizeSite($line);
            if ($sanitized) {
                $sites[$sanitized] = $sanitized;
            }
        }

        return array_values($sites);
    }

    /**
     * Expose default site list for admin utilities.
     */
    public static function getDefaultSiteList(): array
    {
        return self::getDefaultSites();
    }
}
