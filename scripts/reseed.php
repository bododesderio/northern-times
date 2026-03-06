<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use App\Services\DB;
$pdo = DB::pdo();

echo "=== SEEDING 100 SOURCES (70 Africa + 30 Global) ===\n\n";

$cats = [];
foreach ($pdo->query("SELECT id, slug FROM categories")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cats[$r['slug']] = $r['id'];
}
$def = $cats['world'] ?? $cats['top-stories'] ?? array_values($cats)[0] ?? null;
function cid(string ...$slugs): ?string {
    global $cats, $def;
    foreach ($slugs as $s) { if (isset($cats[$s])) return $cats[$s]; }
    return $def;
}
echo count($cats) . " categories found\n\n";

// [name, feed_url, website_url, tag, default_category_id, crawl_interval]
$sources = [
    // UGANDA (20)
    ['Daily Monitor','https://www.monitor.co.ug/resource/rss/News.xml','https://www.monitor.co.ug','uganda',cid('northern-uganda','politics'),15],
    ['New Vision','https://www.newvision.co.ug/feed','https://www.newvision.co.ug','uganda',cid('politics','top-stories'),15],
    ['The Observer Uganda','https://observer.ug/feed','https://observer.ug','uganda',cid('politics','top-stories'),15],
    ['Uganda Radio Network','https://ugandaradionetwork.net/feed','https://ugandaradionetwork.net','uganda',cid('northern-uganda','politics'),15],
    ['Nile Post','https://nilepost.co.ug/feed/','https://nilepost.co.ug','uganda',cid('politics','top-stories'),15],
    ['The Independent Uganda','https://www.independent.co.ug/feed/','https://www.independent.co.ug','uganda',cid('politics','opinion'),15],
    ['Chimpreports','https://chimpreports.com/feed/','https://chimpreports.com','uganda',cid('politics','top-stories'),15],
    ['Softpower News','https://www.softpower.ug/feed/','https://www.softpower.ug','uganda',cid('politics','top-stories'),15],
    ['Eagle Online','https://eagle.co.ug/feed','https://eagle.co.ug','uganda',cid('politics','top-stories'),15],
    ['PML Daily','https://www.pmldaily.com/feed','https://www.pmldaily.com','uganda',cid('politics','business'),15],
    ['Campus Bee','https://campusbee.ug/feed/','https://campusbee.ug','uganda',cid('top-stories','technology'),15],
    ['Business Focus','https://businessfocus.co.ug/feed/','https://businessfocus.co.ug','uganda',cid('business'),15],
    ['Uganda Business News','https://ugbusiness.com/feed','https://ugbusiness.com','uganda',cid('business'),15],
    ['Dokolo Post','https://dokolopost.com/feed/','https://dokolopost.com','uganda',cid('northern-uganda'),15],
    ['Acholi Times','https://acholitimes.com/feed/','https://acholitimes.com','uganda',cid('northern-uganda'),15],
    ['Red Pepper Uganda','https://redpepper.co.ug/feed/','https://redpepper.co.ug','uganda',cid('top-stories','politics'),15],
    ['Sqoop','https://www.sqoop.co.ug/feed','https://www.sqoop.co.ug','uganda',cid('politics','top-stories'),15],
    ['Matooke Republic','https://www.matooke-republic.com/feed/','https://www.matooke-republic.com','uganda',cid('top-stories'),15],
    ['UG Standard','https://ugstandard.com/feed/','https://ugstandard.com','uganda',cid('top-stories','politics'),15],
    ['The Tower Post','https://thetowerpost.com/feed/','https://thetowerpost.com','uganda',cid('top-stories','politics'),15],
    // EAST AFRICA (20)
    ['The East African','https://www.theeastafrican.co.ke/rss.xml','https://www.theeastafrican.co.ke','africa',cid('politics','world'),20],
    ['Daily Nation Kenya','https://nation.africa/rss.xml','https://nation.africa','africa',cid('politics','world'),20],
    ['The Standard Kenya','https://www.standardmedia.co.ke/rss/headlines.php','https://www.standardmedia.co.ke','africa',cid('politics','world'),20],
    ['The Star Kenya','https://www.the-star.co.ke/rss','https://www.the-star.co.ke','africa',cid('politics','world'),20],
    ['The Citizen Tanzania','https://www.thecitizen.co.tz/rss.xml','https://www.thecitizen.co.tz','africa',cid('politics','world'),20],
    ['Daily News Tanzania','https://dailynews.co.tz/feed/','https://dailynews.co.tz','africa',cid('politics','world'),20],
    ['The New Times Rwanda','https://www.newtimes.co.rw/feed','https://www.newtimes.co.rw','africa',cid('politics','world'),20],
    ['KT Press Rwanda','https://www.ktpress.rw/feed/','https://www.ktpress.rw','africa',cid('politics','world'),20],
    ['The Chronicle Rwanda','https://www.chronicles.rw/feed/','https://www.chronicles.rw','africa',cid('politics','world'),20],
    ['Tuko Kenya','https://www.tuko.co.ke/feed','https://www.tuko.co.ke','africa',cid('top-stories','world'),20],
    ['Capital FM Kenya','https://www.capitalfm.co.ke/news/feed/','https://www.capitalfm.co.ke','africa',cid('politics','world'),20],
    ['Citizen Digital Kenya','https://www.citizen.digital/feed','https://www.citizen.digital','africa',cid('top-stories','world'),20],
    ['Garowe Online Somalia','https://www.garoweonline.com/en/feed','https://www.garoweonline.com','africa',cid('politics','world'),20],
    ['Ethiopian Monitor','https://ethiopianmonitor.com/feed/','https://ethiopianmonitor.com','africa',cid('politics','world'),20],
    ['Addis Standard','https://addisstandard.com/feed/','https://addisstandard.com','africa',cid('politics','world'),20],
    ['The Reporter Ethiopia','https://www.thereporterethiopia.com/feed/','https://www.thereporterethiopia.com','africa',cid('politics','world'),20],
    ['Busiweek EA','https://www.busiweek.com/feed/','https://www.busiweek.com','africa',cid('business'),20],
    ['South Sudan News Now','https://www.ssnewsnow.com/feed/','https://www.ssnewsnow.com','africa',cid('politics','world'),20],
    ['Juba Monitor','https://www.jubamonitor.com/feed/','https://www.jubamonitor.com','africa',cid('politics','world'),20],
    ['Eye Radio S Sudan','https://www.eyeradio.org/feed/','https://www.eyeradio.org','africa',cid('politics','world'),20],
    // AFRICA-WIDE (30)
    ['Africa News','https://www.africanews.com/feed/','https://www.africanews.com','africa',cid('world','politics'),20],
    ['All Africa','https://allafrica.com/tools/headlines/rdf/latest/headlines.rdf','https://allafrica.com','africa',cid('world','politics'),20],
    ['The Africa Report','https://www.theafricareport.com/feed/','https://www.theafricareport.com','africa',cid('world','business'),20],
    ['African Arguments','https://africanarguments.org/feed/','https://africanarguments.org','africa',cid('opinion','politics'),20],
    ['African Business','https://african.business/feed/','https://african.business','africa',cid('business'),20],
    ['Mail and Guardian SA','https://mg.co.za/feed/','https://mg.co.za','africa',cid('world','politics'),25],
    ['News24 SA','https://feeds.news24.com/articles/News24/TopStories/rss','https://www.news24.com','africa',cid('world','top-stories'),25],
    ['TimesLIVE SA','https://www.timeslive.co.za/rss/','https://www.timeslive.co.za','africa',cid('world','politics'),25],
    ['Daily Maverick SA','https://www.dailymaverick.co.za/feed/','https://www.dailymaverick.co.za','africa',cid('politics','opinion'),25],
    ['IOL SA','https://www.iol.co.za/cmlink/iol-rss-1.704','https://www.iol.co.za','africa',cid('world','top-stories'),25],
    ['Punch Nigeria','https://punchng.com/feed/','https://punchng.com','africa',cid('world','politics'),25],
    ['Premium Times Nigeria','https://www.premiumtimesng.com/feed','https://www.premiumtimesng.com','africa',cid('world','politics'),25],
    ['The Guardian Nigeria','https://guardian.ng/feed/','https://guardian.ng','africa',cid('world','politics'),25],
    ['Vanguard Nigeria','https://www.vanguardngr.com/feed/','https://www.vanguardngr.com','africa',cid('world','politics'),25],
    ['This Day Nigeria','https://www.thisdaylive.com/feed','https://www.thisdaylive.com','africa',cid('world','business'),25],
    ['Ghana Web','https://www.ghanaweb.com/GhanaHomePage/NewsArchive/rss.xml','https://www.ghanaweb.com','africa',cid('world','politics'),25],
    ['Joy Online Ghana','https://www.myjoyonline.com/feed/','https://www.myjoyonline.com','africa',cid('world','top-stories'),25],
    ['Citi Newsroom Ghana','https://citinewsroom.com/feed/','https://citinewsroom.com','africa',cid('world','politics'),25],
    ['Jeune Afrique','https://www.jeuneafrique.com/feed/','https://www.jeuneafrique.com','africa',cid('world','politics'),25],
    ['RFI Africa','https://www.rfi.fr/en/africa/rss','https://www.rfi.fr','africa',cid('world','politics'),20],
    ['VOA Africa','https://www.voanews.com/api/z-qoeqemiqy','https://www.voanews.com','africa',cid('world','politics'),20],
    ['Quartz Africa','https://qz.com/africa/feed','https://qz.com','africa',cid('world','business'),25],
    ['Face2Face Africa','https://face2faceafrica.com/feed','https://face2faceafrica.com','africa',cid('world','top-stories'),25],
    ['New African Magazine','https://newafricanmagazine.com/feed/','https://newafricanmagazine.com','africa',cid('world','politics'),25],
    ['TechCabal','https://techcabal.com/feed/','https://techcabal.com','africa',cid('technology'),20],
    ['Disrupt Africa','https://disrupt-africa.com/feed/','https://disrupt-africa.com','africa',cid('technology','business'),20],
    ['Techmoran','https://techmoran.com/feed/','https://techmoran.com','africa',cid('technology'),20],
    ['IT News Africa','https://www.itnewsafrica.com/feed/','https://www.itnewsafrica.com','africa',cid('technology'),20],
    ['African Exponent','https://www.africanexponent.com/rss','https://www.africanexponent.com','africa',cid('world','top-stories'),25],
    ['Africanews Sport','https://www.africanews.com/sport/feed/','https://www.africanews.com','africa',cid('sports'),20],
    // GLOBAL (30)
    ['BBC World','https://feeds.bbci.co.uk/news/world/rss.xml','https://www.bbc.com/news','world',cid('world'),20],
    ['BBC Africa','https://feeds.bbci.co.uk/news/world/africa/rss.xml','https://www.bbc.com/news','world',cid('world','politics'),15],
    ['Reuters World','https://www.reutersagency.com/feed/?best-topics=world','https://www.reuters.com','world',cid('world'),20],
    ['Al Jazeera','https://www.aljazeera.com/xml/rss/all.xml','https://www.aljazeera.com','world',cid('world'),20],
    ['AP News','https://rsshub.app/apnews/topics/apf-topnews','https://apnews.com','world',cid('world','top-stories'),20],
    ['CNN World','http://rss.cnn.com/rss/edition_world.rss','https://edition.cnn.com','world',cid('world'),20],
    ['The Guardian World','https://www.theguardian.com/world/rss','https://www.theguardian.com','world',cid('world'),25],
    ['The Guardian Africa','https://www.theguardian.com/world/africa/rss','https://www.theguardian.com','world',cid('world','politics'),20],
    ['NPR News','https://feeds.npr.org/1001/rss.xml','https://www.npr.org','world',cid('world'),25],
    ['NBC News','https://feeds.nbcnews.com/nbcnews/public/world','https://www.nbcnews.com','world',cid('world'),25],
    ['France24 Africa','https://www.france24.com/en/africa/rss','https://www.france24.com','world',cid('world','politics'),20],
    ['DW Africa','https://rss.dw.com/xml/rss-en-africa','https://www.dw.com','world',cid('world','politics'),20],
    ['The Telegraph','https://www.telegraph.co.uk/rss.xml','https://www.telegraph.co.uk','world',cid('world','top-stories'),25],
    ['Sky News World','https://feeds.skynews.com/feeds/rss/world.xml','https://news.sky.com','world',cid('world'),25],
    ['ABC Australia','https://www.abc.net.au/news/feed/51120/rss.xml','https://www.abc.net.au/news','world',cid('world','top-stories'),25],
    ['CNBC','https://www.cnbc.com/id/100003114/device/rss/rss.html','https://www.cnbc.com','world',cid('business'),25],
    ['Bloomberg','https://feeds.bloomberg.com/markets/news.rss','https://www.bloomberg.com','world',cid('business'),25],
    ['TechCrunch','https://techcrunch.com/feed/','https://techcrunch.com','world',cid('technology'),25],
    ['The Verge','https://www.theverge.com/rss/index.xml','https://www.theverge.com','world',cid('technology'),25],
    ['ESPN','https://www.espn.com/espn/rss/news','https://www.espn.com','world',cid('sports'),25],
    ['Sky Sports','https://www.skysports.com/rss/12040','https://www.skysports.com','world',cid('sports'),25],
    ['WHO Health','https://www.who.int/rss-feeds/news-english.xml','https://www.who.int','world',cid('health'),25],
    ['Medical News Today','https://www.medicalnewstoday.com/newsfeeds/rss','https://www.medicalnewstoday.com','world',cid('health'),25],
    ['Nature News','https://www.nature.com/nature.rss','https://www.nature.com','world',cid('technology','health'),25],
    ['The Conversation Africa','https://theconversation.com/africa/articles.atom','https://theconversation.com','world',cid('opinion','world'),20],
    ['Brookings Africa','https://www.brookings.edu/topic/africa/feed/','https://www.brookings.edu','world',cid('opinion','politics'),25],
    ['UN News Africa','https://news.un.org/feed/subscribe/en/news/region/africa/feed/rss.xml','https://news.un.org','world',cid('world','politics'),20],
    ['Amnesty International','https://www.amnesty.org/en/feed.xml','https://www.amnesty.org','world',cid('world','politics'),25],
    ['CFR Africa','https://www.cfr.org/rss/africa','https://www.cfr.org','world',cid('opinion','politics'),25],
    ['The Economist','https://www.economist.com/middle-east-and-africa/rss.xml','https://www.economist.com','world',cid('world','business'),25],
];

$sql = "INSERT INTO crawl_sources
    (name, feed_url, website_url, source_type, is_active,
     crawl_interval, default_category_id, category_map,
     keyword_include, keyword_exclude, max_articles, strip_selectors,
     attribution_text, nofollow, download_images, full_page_scrape)
    VALUES
    (:name, :feed_url, :website_url, 'rss', true,
     :interval, :cat, '{}',
     NULL, NULL, :max_art, NULL,
     :attr, true, true, true)";

$stmt = $pdo->prepare($sql);
$total = 0; $ug = $af = $gl = 0;

foreach ($sources as [$name, $feed, $web, $tag, $catId, $interval]) {
    $maxArt = ($tag === 'uganda') ? 30 : 20;
    try {
        $stmt->execute([
            ':name' => $name, ':feed_url' => $feed, ':website_url' => $web,
            ':interval' => $interval, ':cat' => $catId,
            ':max_art' => $maxArt, ':attr' => 'Source: ' . $name,
        ]);
        $total++;
        match($tag) { 'uganda' => $ug++, 'africa' => $af++, default => $gl++ };
        echo "  + [{$tag}] {$name}\n";
    } catch (\Throwable $e) {
        echo "  x {$name}: " . substr($e->getMessage(), 0, 80) . "\n";
    }
}

echo "\n=== SEEDED {$total} SOURCES ===\n";
echo "Uganda: {$ug} | Africa: {$af} | Global: {$gl}\n";
echo "\nRun: php /var/www/html/cron/crawl.php\n";