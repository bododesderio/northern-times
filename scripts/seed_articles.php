<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use App\Services\DB;
$pdo = DB::pdo();

$adminId = '881ac471-e40e-4096-9907-3630b291e19b';

echo "=== SEEDING ARTICLES WITH IMAGES FOR ALL 19 CATEGORIES ===\n\n";

// Load categories
$cats = [];
foreach ($pdo->query("SELECT id, name, slug FROM categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cats[$r['slug']] = ['id' => $r['id'], 'name' => $r['name']];
}
$def = $cats['world'] ?? array_values($cats)[0] ?? null;
echo count($cats) . " categories loaded\n\n";

// ── HELPERS ──────────────────────────────────────────────────────
function makeSlug(string $title): string {
    $s = strtolower(trim($title));
    $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
    $s = preg_replace('/[\s-]+/', '-', $s);
    return substr($s, 0, 200);
}

function img(string $seed, int $w = 1200, int $h = 630): string {
    return "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
}

function buildContent(string $intro, string $slug, string $catName): string {
    $img1 = img("{$slug}-mid1", 800, 450);
    $img2 = img("{$slug}-mid2", 800, 450);
    $img3 = img("{$slug}-mid3", 800, 450);

    return <<<HTML
<p>{$intro} The developments have attracted attention from stakeholders across the country and beyond, with experts weighing in on the implications for Uganda's future development trajectory.</p>

<p>According to officials familiar with the matter, the initiative represents a significant step forward in addressing long-standing challenges that have affected communities across the region. The announcement was met with cautious optimism by civil society organizations who have been advocating for action on this issue for several years.</p>

<p>"We have been pushing for this kind of decisive action for a long time," said a prominent civil society leader. "What matters now is the quality of implementation and the commitment to transparency at every level of delivery."</p>

<h2>Background and Context</h2>

<figure><img src="{$img1}" alt="{$catName} related image" style="width:100%;height:auto;border-radius:6px;"><figcaption>Photo: The situation on the ground continues to evolve as stakeholders push for change.</figcaption></figure>

<p>The situation has been developing over several months, with various stakeholders engaging in intensive consultations to find workable solutions. Government officials, development partners, community leaders and technical experts have all contributed to shaping the approach that has now been adopted.</p>

<p>Previous attempts to address similar challenges have met with mixed results, but analysts believe the current approach benefits from lessons learned and a more comprehensive understanding of the underlying issues. The involvement of local communities in the planning process has been cited as a key factor in building support for the initiative.</p>

<p>Historical data suggests that interventions of this nature require sustained commitment over multiple years to achieve lasting impact. Research conducted by the Uganda Bureau of Statistics indicates that similar programmes in neighbouring countries have taken between three and seven years to demonstrate measurable outcomes at scale.</p>

<p>"This is a milestone moment for our country," said a senior government official who spoke at the launch event. "We have listened to the concerns of our people and we are taking decisive action to ensure that the benefits of development reach every corner of Uganda."</p>

<h2>Implementation Details</h2>

<p>The implementation plan spans multiple phases with the first phase focusing on the most critical areas identified through extensive baseline assessments. Technical teams have been deployed across the target areas to ensure smooth execution and timely delivery of results.</p>

<p>Funding for the initiative has been secured through a combination of government budget allocations, development partner support and innovative financing mechanisms. The total investment is expected to generate significant returns both in economic terms and in improved quality of life for affected populations.</p>

<figure><img src="{$img2}" alt="Implementation progress" style="width:100%;height:auto;border-radius:6px;"><figcaption>Teams are working across multiple regions to ensure comprehensive coverage and effective delivery.</figcaption></figure>

<p>Monitoring and evaluation frameworks have been established to track progress and ensure accountability throughout the implementation period. Regular reports will be published to keep the public informed about achievements, challenges and adjustments to the programme.</p>

<p>A dedicated coordination unit has been established to oversee implementation, with representation from key government ministries, development partners and civil society organizations. The unit will meet weekly to review progress and address any emerging challenges that could affect delivery timelines.</p>

<h2>Stakeholder Reactions</h2>

<p>Reactions from various stakeholders have been largely positive, though some have raised questions about the timeline and resource allocation. Opposition lawmakers have called for greater parliamentary oversight while commending the overall direction of the initiative.</p>

<p>Development partners including the World Bank, African Development Bank and various bilateral donors have expressed support for the approach, noting its alignment with international best practices and Uganda's national development goals under the Vision 2040 framework.</p>

<p>Community leaders in the affected areas have welcomed the development, expressing hope that it will translate into tangible improvements in their daily lives. Several have volunteered to serve on local oversight committees to ensure effective implementation at the grassroots level.</p>

<blockquote><p>"For too long, our communities have waited for meaningful change. This initiative gives us real hope that our voices are being heard and our needs are being prioritized in national planning," said a community leader from the Northern region.</p></blockquote>

<h2>Economic Impact Assessment</h2>

<p>Economic analysts project that the initiative will contribute significantly to GDP growth over the medium term while creating employment opportunities for thousands of Ugandans, particularly young people and women who have been disproportionately affected by existing challenges.</p>

<figure><img src="{$img3}" alt="Economic development" style="width:100%;height:auto;border-radius:6px;"><figcaption>The economic ripple effects are expected to benefit communities well beyond the immediate target areas.</figcaption></figure>

<p>The multiplier effects of the investment are expected to extend well beyond the direct beneficiaries, stimulating local economies through increased demand for goods and services. Small and medium enterprises in the target areas are already positioning themselves to take advantage of the anticipated economic activity.</p>

<p>Financial institutions have indicated their willingness to provide complementary financing for businesses and individuals seeking to participate in the economic opportunities created by the programme. Several banks have announced dedicated credit facilities aligned with the initiative objectives.</p>

<h2>Looking Ahead</h2>

<p>As implementation gets underway, attention is turning to the broader implications of this initiative for Uganda's development trajectory. If successful, the model could be replicated in other sectors and regions, contributing to the country's ambitious development goals.</p>

<p>Experts emphasize that sustained political commitment, adequate funding, community participation and strong institutional frameworks will be essential for achieving the desired outcomes. The coming months will be critical in establishing the foundations for long-term success.</p>

<p>The initiative is expected to create thousands of direct and indirect employment opportunities while building local capacity that will continue to benefit communities long after the formal programme concludes. Training and skills development components have been embedded throughout the implementation plan to ensure sustainability.</p>

<p>Regular public updates will be provided through government communication channels, media briefings and community meetings to maintain transparency and accountability throughout the implementation process. An independent evaluation is scheduled for the end of the first year to assess early results and inform any necessary adjustments to the programme design.</p>
HTML;
}

// ── ARTICLE DATA: [title, intro] per category ───────────────────
$articles = [

'top-stories' => [
    ['Uganda GDP Growth Surpasses East African Neighbours in Latest Quarter','Uganda has posted a remarkable 6.2% GDP growth in the latest quarter, outperforming Kenya, Tanzania and Rwanda according to new data released by the Ministry of Finance.'],
    ['Parliament Passes Landmark Digital Economy Bill After Heated Debate','After three days of intense deliberation, Parliament has passed the Digital Economy Bill 2026 which aims to regulate electronic commerce, data protection and digital financial services across the country.'],
    ['President Announces Major Infrastructure Investment Package for Northern Region','A comprehensive UGX 4.5 trillion infrastructure package targeting roads, bridges, hospitals and schools in the Northern region has been unveiled during a special address to the nation.'],
    ['East African Community Summit Yields Historic Trade Agreement','Leaders from all seven EAC member states have signed an unprecedented trade facilitation agreement that will eliminate remaining tariff barriers and harmonize customs procedures by 2028.'],
    ['National Census Results Reveal Dramatic Urban Migration Trends','The 2026 National Census has revealed that urban areas now account for 32% of the population, up from 24% a decade ago, with Kampala, Gulu and Mbarara seeing the highest growth rates.'],
    ['Uganda Airlines Launches Direct Flights to London and Mumbai','Uganda Airlines has announced the commencement of direct flights connecting Entebbe to London Heathrow and Mumbai, marking a significant expansion of the national carrier international route network.'],
    ['Central Bank Holds Interest Rate Steady Amid Inflation Concerns','The Bank of Uganda has maintained the central bank rate at 10.25% citing the need to balance economic growth with rising inflationary pressures driven by global commodity prices.'],
    ['Major Oil Discovery in Albertine Graben Boosts Production Estimates','A significant new oil discovery in the Albertine Graben region has increased total estimated recoverable reserves by 15%, potentially accelerating Uganda first oil production timeline.'],
    ['Government Launches Universal Health Coverage Programme in 45 Districts','The Ministry of Health has officially rolled out Phase One of the Universal Health Coverage programme covering 45 districts with free primary healthcare services for all citizens.'],
    ['Kampala-Jinja Expressway Officially Opens to Public Traffic','The long-awaited 77-kilometre Kampala-Jinja Expressway has been officially opened, reducing travel time between the two cities from three hours to just 45 minutes.'],
    ['Uganda Qualifies for African Cup of Nations After Historic Victory','The Uganda Cranes have secured their spot at the 2027 Africa Cup of Nations with a thrilling 2-1 victory over Cameroon in the final qualifying match at Namboole Stadium.'],
    ['Refugee Population in Uganda Reaches 1.7 Million as Regional Conflicts Persist','UNHCR reports that Uganda now hosts 1.7 million refugees, making it the largest refugee-hosting country in Africa, with new arrivals from DRC and South Sudan continuing.'],
    ['Coffee Exports Hit Record USD 1.2 Billion as Global Demand Surges','Uganda coffee exports have reached a historic USD 1.2 billion in the current financial year driven by strong global demand and premium prices for Ugandan robusta and arabica.'],
    ['Supreme Court Rules on Landmark Land Rights Case Affecting Thousands','The Supreme Court has issued a landmark ruling on communal land rights that will affect thousands of families in Northern Uganda, establishing new precedents for customary land tenure.'],
    ['National Water Corporation Expands Coverage to 80 Percent of Urban Areas','NWSC has announced that clean water coverage in urban areas has reached 80%, up from 72% last year, following completion of major water treatment plants in six towns.'],
],

'northern-uganda' => [
    ['Gulu City Council Approves Ambitious Urban Development Master Plan','The Gulu City Council has unanimously approved a comprehensive 15-year master plan that envisions transforming the city into a major commercial hub for the Greater North region.'],
    ['Acholi Cultural Leaders Launch Heritage Preservation Initiative','The Ker Kwaro Acholi has launched a multi-million shilling initiative to document, preserve and promote Acholi cultural heritage including language, traditional music and oral histories.'],
    ['New Agro-Processing Plant in Lira to Create 2000 Jobs','A state-of-the-art agricultural processing facility being constructed in Lira Industrial Park is expected to create over 2,000 direct jobs and benefit more than 15,000 smallholder farmers.'],
    ['Northern Uganda Education Fund Distributes Scholarships to 5000 Students','The Northern Uganda Education Fund has distributed full scholarships to 5,000 students from disadvantaged backgrounds across Acholi, Lango, Teso and West Nile sub-regions.'],
    ['Karuma Hydropower Dam Begins Full Capacity Operations','The 600MW Karuma Hydropower Dam on the River Nile has finally begun operating at full capacity, significantly boosting Uganda electricity generation and expected to reduce power costs by 30%.'],
    ['Cross-Border Trade Flourishes at Elegu as South Sudan Peace Holds','Trade volumes at the Elegu border crossing have tripled over the past year as the relative peace in South Sudan has encouraged merchants on both sides to resume commercial activities.'],
    ['Apaa Land Conflict Mediation Reaches Breakthrough Agreement','After years of conflict between Acholi and Madi communities over the disputed Apaa land, government mediators have announced a breakthrough agreement that satisfies both parties.'],
    ['Gulu University Medical School Receives International Accreditation','Gulu University Faculty of Medicine has been granted full international accreditation, becoming the first medical school in Northern Uganda to achieve this prestigious milestone.'],
    ['Shea Butter Cooperative in West Nile Exports First Container to Europe','A women cooperative in West Nile has achieved a historic milestone by exporting their first full container of organic shea butter products to markets in Germany and the Netherlands.'],
    ['Railway Revival: Tororo-Gulu Line Rehabilitation Begins','Construction crews have begun the rehabilitation of the Tororo-Gulu railway line, a project expected to dramatically reduce transportation costs for agricultural produce from the North.'],
    ['Lamwo District Launches Community-Based Tourism Programme','Lamwo District has launched an innovative community-based tourism programme centered around Kidepo Valley National Park, aiming to bring tourism revenue directly to local communities.'],
    ['IDP Resettlement Programme Marks 95 Percent Completion in Acholi Sub-Region','The government internally displaced persons resettlement programme has reached 95% completion in the Acholi sub-region, with the remaining families expected to be resettled by year end.'],
    ['Northern Uganda Youth Innovation Hub Opens in Gulu','A modern technology and innovation hub has opened in Gulu City, providing young entrepreneurs with access to high-speed internet, co-working spaces, mentorship and seed funding.'],
    ['Nwoya District Hospital Receives First MRI Machine in Sub-Region','Nwoya District Hospital has received and installed the first MRI machine in the entire Northern sub-region, eliminating the need for patients to travel to Kampala for advanced imaging.'],
    ['Traditional Acholi Bee-Keeping Attracts International Research Interest','International researchers from the University of Oxford have begun studying traditional Acholi bee-keeping practices which they believe hold valuable insights for sustainable agriculture.'],
],

'business' => [
    ['MTN Uganda Reports Record Profits as Mobile Money Transactions Surge','MTN Uganda has posted record annual profits of UGX 890 billion driven by a 45% increase in mobile money transactions and steady growth in data revenue across the country.'],
    ['Kampala Stock Exchange Welcomes Three New Listings in Single Month','The Uganda Securities Exchange has welcomed three new company listings in a single month, bringing total market capitalization above UGX 30 trillion for the first time.'],
    ['East African Brewery Invests USD 50 Million in Jinja Expansion','East African Breweries has announced a USD 50 million expansion of its Jinja production facility, doubling capacity to meet growing demand across Uganda and export markets.'],
    ['Fintech Startup Raises USD 15 Million in Series B Funding Round','Kampala-based fintech company PayWay has raised USD 15 million in Series B funding led by a consortium of African and international venture capital firms to expand across East Africa.'],
    ['Uganda Revenue Authority Surpasses Collection Target by 8 Percent','URA has collected UGX 25.3 trillion in the current financial year, exceeding the target by 8% and marking the highest revenue collection performance in the authority history.'],
    ['Commercial Banks Reduce Lending Rates Following Central Bank Guidance','Major commercial banks including Stanbic, Standard Chartered and DFCU have announced reductions in prime lending rates following the Bank of Uganda moral suasion campaign.'],
    ['Chinese Investors Eye Uganda Manufacturing Sector with USD 200 Million Pledge','A delegation of Chinese manufacturers has pledged USD 200 million in investments targeting textiles, steel fabrication and electronics assembly plants in Uganda industrial parks.'],
    ['Small Business Federation Reports 40 Percent Growth in Women-Owned Enterprises','The Uganda Small Business Federation reports that women-owned enterprises have grown by 40% over three years, driven by improved access to microfinance and business training.'],
    ['Coffee Value Addition Initiative Doubles Processed Export Volumes','The government coffee value addition initiative has achieved its target of doubling processed coffee export volumes, with specialty and roasted coffee now commanding premium prices.'],
    ['Insurance Penetration Reaches Historic 2 Percent as Digital Products Gain Traction','Uganda insurance penetration has reached 2% for the first time, driven by innovative digital microinsurance products that have made coverage accessible to lower-income populations.'],
    ['Mukono Industrial Park Attracts Fifth Major International Manufacturer','The Mukono Industrial Park has attracted its fifth major international manufacturer, a South Korean electronics assembly plant expected to employ 1,500 workers upon full operation.'],
    ['Agricultural Commodity Exchange Reports Record Trading Volumes','The Uganda Commodity Exchange has reported record trading volumes for maize, beans and sesame, with digital trading platforms connecting smallholder farmers directly to bulk buyers.'],
    ['Telecom Regulator Approves New Mobile Network Operator Licence','The Uganda Communications Commission has approved a licence for a fourth mobile network operator, expected to increase competition and drive down voice and data prices nationwide.'],
    ['Real Estate Investment Trust Framework Launched to Boost Property Sector','The Capital Markets Authority has launched a framework for REITs enabling Ugandans to invest in commercial property portfolios through the securities exchange for the first time.'],
    ['Uganda Export Board Targets USD 8 Billion in Annual Exports by 2030','The Uganda Export Promotion Board has unveiled an ambitious strategy targeting USD 8 billion in annual exports by 2030, focusing on value-added agricultural products and minerals.'],
],

'opinion' => [
    ['Why Uganda Must Invest in Technical Education to Drive Industrialisation','The path to industrialisation is paved not just with capital investment and infrastructure, but fundamentally with skilled human resources capable of operating and maintaining modern manufacturing systems.'],
    ['The Case for Decentralising Health Services to Sub-County Level','As Uganda pursues universal health coverage, the argument for pushing primary healthcare delivery down to the sub-county level grows stronger with each passing day and each preventable death.'],
    ['Digital Literacy Should Be a National Priority Not an Urban Privilege','While Kampala youth navigate smartphones with ease, millions of young Ugandans in rural areas remain digitally illiterate in an increasingly connected world that will not wait for them to catch up.'],
    ['Land Reform Cannot Wait: The Growing Crisis of Landlessness','The unresolved land question in Northern Uganda continues to simmer beneath the surface of apparent peace, threatening to erupt into conflicts that could unravel years of post-war recovery.'],
    ['Regional Integration Is Not Optional: Why EAC Must Succeed','The East African Community represents perhaps the most promising pathway to prosperity for 300 million people, yet political rivalries continue to undermine genuine integration.'],
    ['Climate Change Is Already Here: Uganda Must Act Now Not Tomorrow','The shifting rainfall patterns, prolonged droughts in Karamoja, and devastating floods in Bududa are not distant predictions but present realities demanding immediate and sustained action.'],
    ['Youth Unemployment: Time for Honest Conversation About What Works','Government youth employment programmes have produced mixed results at best. It is time for an honest assessment of what actually works and what merely looks good in policy documents.'],
    ['The Silent Crisis: Mental Health in Post-Conflict Northern Uganda','Two decades after the LRA insurgency, the psychological wounds remain largely unaddressed, creating a hidden epidemic of trauma, depression and substance abuse across the region.'],
    ['Why Press Freedom Matters More Than Ever in the Digital Age','As social media becomes a primary news source for millions, the role of professional independent journalism in holding power accountable has never been more critical to democratic governance.'],
    ['Agricultural Transformation Requires More Than Slogans and Seeds','Every government in Uganda history has declared agriculture the backbone of the economy. Yet farmers remain poor because declarations alone cannot substitute for genuine structural change.'],
    ['Lessons from Rwanda: What Uganda Can Learn About Governance Efficiency','Rwanda rapid development offers useful lessons for Uganda, not in political systems but in demonstrating what determined implementation of clear policies can achieve in practice.'],
    ['The Infrastructure Deficit: Why Roads Alone Will Not Transform the Economy','While road construction is important, Uganda fixation on asphalt comes at the expense of equally critical investments in railway networks, inland water transport and digital infrastructure.'],
    ['Protecting the Environment Is Not Anti-Development','The false choice between environmental conservation and economic development serves only those who profit from the unsustainable extraction of natural resources at the expense of future generations.'],
    ['Corruption: The Tax That Falls Heaviest on the Poor','When a health centre has no drugs because funds were diverted, when a school has no textbooks because procurement was rigged, it is the poorest Ugandans who bear the true cost of corruption.'],
    ['Education Quality Not Just Access Should Be Our Measure of Progress','Uganda has achieved impressive gains in school enrollment. But having children physically present in classrooms means little if they are not actually learning the skills they need for life.'],
],

'politics' => [
    ['Electoral Commission Begins Preparations for 2026 General Elections','The Electoral Commission has officially commenced preparations for the 2026 general elections with the announcement of a revised roadmap including voter registration updates and boundary demarcation.'],
    ['Opposition Coalition Announces Joint Platform for Constitutional Reforms','Five opposition parties have formed an unprecedented coalition to push for constitutional reforms including presidential term limits, electoral commission independence and judicial appointments.'],
    ['Cabinet Reshuffle Brings Fresh Faces to Key Ministries','A major cabinet reshuffle has seen the appointment of new ministers in Finance, Health, Education and Defence, signaling a shift in government priorities and a reconfiguration of political alliances.'],
    ['Local Government Elections in 12 Districts Proceed Peacefully','By-elections held in 12 districts across the country have been conducted peacefully with voter turnout averaging 65%, a significant improvement over previous local government elections.'],
    ['Parliament Select Committee Probes Government Borrowing Practices','A Parliamentary select committee has launched a comprehensive investigation into government external borrowing practices amid concerns about rising debt levels approaching 50% of GDP.'],
    ['Inter-Party Dialogue Yields Agreement on Electoral Reform Timeline','Government and opposition negotiators have reached a landmark agreement on a timeline for implementing key electoral reforms ahead of the next general election cycle.'],
    ['Decentralisation Policy Under Review as Districts Multiply Beyond 150','The government has initiated a comprehensive review of its decentralisation policy as the number of administrative districts continues to grow, raising serious questions about governance efficiency.'],
    ['Anti-Corruption Court Convicts Senior Government Official','The Anti-Corruption Court has convicted a senior government official on charges of embezzlement and abuse of office in what prosecutors describe as a landmark case for judicial independence.'],
    ['Regional Governors Conference Addresses Service Delivery Challenges','Governors and chief administrative officers from all regions have convened to address persistent challenges in public service delivery including staffing shortages and budget constraints.'],
    ['Political Parties Registration Exercise Begins Ahead of Election Cycle','The Electoral Commission has begun a mandatory re-registration exercise for all political parties, with new requirements for demonstrated nationwide presence and financial accountability.'],
    ['Women Representation Bill Seeks to Expand Female Political Participation','A private member bill seeking to expand women representation beyond the current constitutional provisions has gained strong support from legislators across party lines.'],
    ['Government Establishes Commission on National Unity and Reconciliation','A new commission focused on national unity and reconciliation has been established by presidential decree, tasked with addressing historical grievances and promoting social cohesion.'],
    ['Youth Wings of Major Parties Demand Greater Inclusion in Decision-Making','Youth leaders from ruling and opposition parties have jointly called for greater inclusion of young people in party decision-making structures and candidate selection processes.'],
    ['Constitutional Court Hears Challenge to Recently Enacted Security Legislation','The Constitutional Court has begun hearing a petition challenging the constitutionality of recently enacted security legislation that critics say infringes on civil liberties.'],
    ['East African Legislative Assembly Passes Resolution on Democratic Standards','EALA has passed a landmark resolution establishing minimum democratic standards for elections across all EAC member states, including requirements for independent electoral bodies.'],
],

'technology' => [
    ['Uganda Launches National Artificial Intelligence Strategy','The Ministry of ICT has unveiled Uganda first comprehensive National AI Strategy, outlining plans to integrate artificial intelligence across agriculture, healthcare and public service delivery.'],
    ['Kampala Tech Hub Ecosystem Surpasses 100 Active Startups','The Kampala technology ecosystem has reached a milestone of over 100 active startups, with combined funding exceeding USD 80 million and generating thousands of jobs for young Ugandans.'],
    ['Mobile Money Interoperability Goes Live Connecting All Networks','The long-awaited mobile money interoperability system has gone live, allowing seamless transfers between MTN, Airtel and all other mobile money platforms for the first time in Uganda.'],
    ['Government Launches Digital ID System Linking All Public Services','A new integrated digital identification system has been launched that links national IDs to healthcare records, tax records, land titles and social security accounts nationwide.'],
    ['Fibre Optic Network Expansion Reaches 80 Percent of District Headquarters','The National Backbone Infrastructure project has connected fibre optic internet to 80% of all district headquarters, dramatically improving internet speeds and reducing connectivity costs.'],
    ['Ugandan Drone Startup Wins Contract for Medical Supply Delivery','A Ugandan drone technology startup has won a major contract to deliver medical supplies to remote health facilities across the Karamoja sub-region using autonomous delivery drones.'],
    ['Cybersecurity Centre of Excellence Established at Makerere University','Makerere University has established East Africa first dedicated Cybersecurity Centre of Excellence with support from the African Development Bank and international technology partners.'],
    ['E-Government Platform Reduces Business Registration Time to 24 Hours','The upgraded Uganda Business Registration System now allows entrepreneurs to register new businesses within 24 hours entirely online, down from the previous average of 21 days.'],
    ['Solar-Powered Internet Kiosks Bring Connectivity to 500 Rural Villages','An innovative programme has deployed solar-powered internet kiosks to 500 previously unconnected rural villages, providing communities with their first access to the digital world.'],
    ['Blockchain-Based Land Registry Pilot Shows Promising Results','A pilot project using blockchain technology for land registration in three districts has shown promising results in reducing fraud and disputes while increasing transparency in land transactions.'],
    ['Uganda Coding Academy Graduates 3000 Software Developers','The Uganda Coding Academy established with government and private sector funding has graduated 3,000 trained software developers, with 75% securing employment within six months.'],
    ['AgriTech Platform Connects 200000 Farmers to Real-Time Market Prices','A locally developed agricultural technology platform now serves over 200,000 farmers, providing real-time market prices, weather forecasts and agronomic advice via SMS and smartphone apps.'],
    ['5G Network Testing Begins in Kampala Metropolitan Area','Telecommunications companies have commenced 5G network testing in the Kampala metropolitan area, with commercial launch expected within 18 months pending regulatory approval.'],
    ['Digital Payment Adoption Surges as Cash Transactions Decline 30 Percent','Digital payment transactions have surged across Uganda with cash-based transactions declining by 30% over two years as businesses and consumers embrace electronic payment systems.'],
    ['Innovation Fund Allocates UGX 50 Billion to Early-Stage Tech Startups','The newly established National Innovation Fund has allocated UGX 50 billion in grants and soft loans to early-stage technology startups working on solutions to local development challenges.'],
],

'world' => [
    ['African Union Summit Addresses Continental Free Trade Implementation','The 39th African Union Summit has focused on accelerating implementation of the African Continental Free Trade Area with leaders committing to eliminate remaining barriers to intra-African trade.'],
    ['United Nations Climate Conference Sets New Emission Reduction Targets','World leaders gathered at the latest UN Climate Conference have agreed on more ambitious emission reduction targets, with developing nations securing increased climate finance commitments.'],
    ['Global Food Prices Rise Sharply Amid Supply Chain Disruptions','The FAO Food Price Index has reached its highest level in two years as conflict, climate events and supply chain disruptions continue to push global food prices upward affecting millions.'],
    ['South Sudan Peace Process Makes Significant Progress After Stalemate','The revitalized peace agreement in South Sudan has shown significant progress with the formation of a unified military command and agreement on transitional election timelines.'],
    ['DRC Conflict Escalation Displaces Additional 500000 People','Renewed fighting in eastern Democratic Republic of Congo has displaced an additional 500,000 people bringing the total internally displaced population in the region to over 6 million.'],
    ['Kenya Launches Ambitious Green Energy Plan Targeting 100 Percent Renewable','Kenya has launched an ambitious plan to achieve 100% renewable energy generation by 2030, building on its already substantial geothermal and wind power infrastructure investments.'],
    ['G20 Leaders Agree on Global Minimum Tax Framework for Multinationals','G20 leaders have reached consensus on implementing a global minimum corporate tax rate of 15% aimed at preventing multinational corporations from shifting profits to tax havens.'],
    ['WHO Declares End to Latest Ebola Outbreak in Central Africa','The World Health Organization has officially declared the end of the latest Ebola outbreak in Central Africa after 42 days without new cases, praising regional health system response.'],
    ['China Belt and Road Initiative Shifts Focus to Green Infrastructure','China has announced a significant shift in its Belt and Road Initiative projects in Africa, redirecting investment toward renewable energy, digital infrastructure and sustainable agriculture.'],
    ['Global Semiconductor Shortage Begins to Ease as New Factories Come Online','The global semiconductor shortage that has affected industries from automotive to consumer electronics is beginning to ease as major new fabrication plants in Asia and Europe come online.'],
    ['International Court of Justice Rules on Maritime Border Dispute','The International Court of Justice has issued a landmark ruling on a maritime border dispute between two East African nations, establishing new precedents for territorial waters.'],
    ['World Bank Report Highlights African Debt Sustainability Challenges','A new World Bank report highlights growing debt sustainability challenges across Sub-Saharan Africa with total external debt reaching USD 700 billion and debt service costs rising.'],
    ['BRICS Expansion Reshapes Global Economic Governance Landscape','The expansion of BRICS to include several new members has fundamentally altered the global economic governance landscape, creating a bloc representing over 40% of the world population.'],
    ['Russian-Ukraine Conflict Continues to Impact Global Grain Markets','The ongoing conflict between Russia and Ukraine continues to disrupt global grain markets with African nations particularly affected by reduced wheat exports and rising bread prices.'],
    ['New International Treaty on Pandemic Preparedness Nears Completion','Negotiators are close to finalizing a new international treaty on pandemic preparedness and response, incorporating lessons learned from COVID-19 to prevent future global health emergencies.'],
],

'health' => [
    ['Uganda Achieves Major Milestone in Malaria Reduction with 40 Percent Drop','Uganda has achieved a 40% reduction in malaria cases over five years through a comprehensive strategy combining indoor spraying, treated bed nets and improved diagnostic capacity.'],
    ['New Maternal Health Programme Reduces Childbirth Deaths by Half','A comprehensive maternal health programme implemented across 60 districts has reduced maternal mortality by 50% through trained midwives, emergency obstetric care and community health workers.'],
    ['Mental Health Services Expansion Reaches Rural Health Centre IVs','The Ministry of Health has expanded mental health services to all Health Centre IV facilities, training clinical officers in basic psychiatric care and establishing referral pathways.'],
    ['COVID-19 Vaccination Coverage Reaches 70 Percent Among Adults','Uganda has achieved 70% COVID-19 vaccination coverage among the adult population, meeting the WHO recommended threshold for population-level immunity against severe disease outcomes.'],
    ['National Cancer Treatment Centre Opens Second Facility in Gulu','The Uganda Cancer Institute has opened its second treatment facility in Gulu, bringing chemotherapy, radiotherapy and surgical services closer to patients in Northern Uganda.'],
    ['Clean Water Initiative Reduces Waterborne Disease by 60 Percent','A joint government-NGO clean water initiative has achieved a 60% reduction in waterborne diseases in 30 target districts through protected water sources and hygiene education campaigns.'],
    ['Uganda Develops First Locally Manufactured Rapid Diagnostic Tests','Ugandan scientists have developed the country first locally manufactured rapid diagnostic tests for malaria and HIV, reducing dependency on imported medical supplies from overseas.'],
    ['Traditional Medicine Integration Programme Gains WHO Recognition','Uganda programme integrating traditional medicine practitioners into the formal health system has gained recognition from the WHO as a model for other African countries to follow.'],
    ['Nutrition Programme Reduces Childhood Stunting by 15 Percent','A targeted nutrition programme combining food supplementation, nutrition education and agricultural support has reduced childhood stunting by 15% in the Northern region over three years.'],
    ['Telemedicine Platform Connects Rural Patients with Specialist Doctors','A new telemedicine platform is connecting patients in remote areas with specialist doctors in Kampala and Mbarara, providing consultations for conditions that previously required costly travel.'],
    ['Uganda Blood Transfusion Service Achieves Self-Sufficiency Target','The Uganda Blood Transfusion Service has achieved its target of collecting sufficient voluntary blood donations to meet national needs without reliance on emergency family donors.'],
    ['HIV Prevention Programme Reports Lowest New Infection Rate in Two Decades','Uganda national HIV prevention programme has reported the lowest rate of new infections in 20 years, with combination prevention strategies showing sustained impact across all age groups.'],
    ['Eye Care Campaign Restores Sight to 10000 Patients in Rural Areas','A nationwide eye care campaign has performed cataract surgeries and provided corrective lenses to over 10,000 patients in rural areas who previously had limited access to eye care.'],
    ['Pharmaceutical Manufacturing Plant Opens for Local Drug Production','Uganda first large-scale pharmaceutical manufacturing plant has commenced production, initially producing essential medicines including antibiotics, antimalarials and pain medications.'],
    ['Community Health Worker Programme Trains 50000 Village Health Teams','The expanded community health worker programme has trained 50,000 village health team members across all districts providing basic health services and education at the community level.'],
],

'sports' => [
    ['Uganda Cranes Captain Named African Footballer of the Year','Uganda Cranes captain has been named the CAF African Footballer of the Year following an outstanding season that saw him lead his club to continental glory and the national team to AFCON.'],
    ['Kampala Marathon Attracts Record 80000 Participants','The annual Kampala Marathon has attracted a record 80,000 participants including elite runners from 30 countries, with proceeds supporting health and education programmes across Uganda.'],
    ['Uganda Cricket Team Qualifies for T20 World Cup for First Time','The Uganda Cricket Cranes have made history by qualifying for the ICC T20 World Cup for the first time, defeating established cricketing nations in the qualifying tournament.'],
    ['National Stadium Renovation Project Reaches 75 Percent Completion','The comprehensive renovation of Mandela National Stadium at Namboole has reached 75% completion with the upgraded facility expected to meet FIFA standards and seat 60,000.'],
    ['Uganda Premier League Secures Major Broadcasting Deal Worth USD 10 Million','The Uganda Premier League has signed a landmark broadcasting deal worth USD 10 million over five years, the largest ever for Ugandan domestic football competition.'],
    ['National Netball Team Retains African Championship Title','The She Cranes have successfully defended their African Netball Championship title with a dominant performance, extending their continental dominance to four consecutive titles.'],
    ['Joshua Cheptegei Breaks Another World Record in 10000 Metres','Uganda distance running star Joshua Cheptegei has broken his own world record in the 10,000 metres at a Diamond League event, shaving two seconds off the previous mark he set.'],
    ['Youth Sports Academy Opens Modern Training Facility in Lira','A new youth sports academy featuring modern training facilities for football, athletics, basketball and swimming has opened in Lira City targeting talent development in Northern Uganda.'],
    ['Uganda Rugby Sevens Team Reaches World Series Quarter-Finals','The Uganda Rugby Sevens team has reached the quarter-finals of a World Rugby Sevens Series event for the first time, defeating traditional powerhouse Samoa in a stunning upset.'],
    ['National Swimming Championships See Multiple Records Broken','The 2026 National Swimming Championships held at the newly built aquatics centre have seen multiple national records broken across various age categories and swimming disciplines.'],
    ['Women Football League Achieves Professional Status','The Uganda Women Premier League has achieved full professional status after securing major corporate sponsorship deals providing player salaries and improved training facilities.'],
    ['Boxing Federation Targets Three Gold Medals at Commonwealth Games','The Uganda Boxing Federation has set an ambitious target of three gold medals at the upcoming Commonwealth Games following impressive performances in continental championship events.'],
    ['Mountain Biking Tourism Event Attracts International Riders to Kapchorwa','An international mountain biking event in Kapchorwa has attracted riders from 25 countries, boosting sports tourism and showcasing Uganda stunning highland terrain to the world.'],
    ['Motorsport Rally Championship Grows with Addition of New Circuits','The Uganda National Rally Championship has expanded with the addition of three new circuits in Western Uganda, attracting increased participation and corporate sponsorship.'],
    ['University Sports League Expansion Includes 15 New Institutions','The National University Sports League has expanded to include 15 additional institutions, bringing the total to 40 universities competing across 12 sporting disciplines nationwide.'],
],

'crime-security' => [
    ['Police Launch Major Operation Against Cross-Border Smuggling Networks','Uganda Police Force has launched Operation Secure Borders targeting organized smuggling networks operating across the porous borders with DRC, South Sudan and Kenya.'],
    ['Anti-Terrorism Unit Foils Planned Attack on Major Infrastructure','The Joint Anti-Terrorism Taskforce has announced the successful prevention of a planned attack on major infrastructure after months of intelligence gathering and surveillance work.'],
    ['Cybercrime Unit Reports 200 Percent Increase in Digital Fraud Cases','The specialized cybercrime unit has reported a 200% increase in reported digital fraud cases including mobile money theft, phishing schemes and online investment scams targeting Ugandans.'],
    ['Community Policing Initiative Reduces Urban Crime by 35 Percent','A community policing initiative piloted in five Kampala divisions has achieved a 35% reduction in reported crimes through improved police-community cooperation and intelligence sharing.'],
    ['Court Sentences Cattle Rustling Ring Leaders to 15 Years','A High Court in Moroto has sentenced the leaders of a major cattle rustling ring to 15 years imprisonment each, marking the most significant conviction in the fight against armed cattle theft.'],
    ['Traffic Police Deploy AI-Powered Cameras for Number Plate Recognition','The Traffic Police department has deployed AI-powered cameras at major junctions in Kampala capable of automatic number plate recognition and traffic violation detection.'],
    ['Interpol Operation Recovers Stolen Cultural Artifacts Worth Millions','An Interpol-coordinated operation has led to the recovery of stolen cultural artifacts valued at millions of dollars being smuggled out of East Africa through international trafficking networks.'],
    ['Prison Reform Programme Shows 40 Percent Reduction in Recidivism','A comprehensive prison reform programme focusing on vocational training, education and rehabilitation has achieved a 40% reduction in recidivism rates among released prisoners.'],
    ['Gender-Based Violence Response Centres Established in All Regions','Dedicated gender-based violence response centres have been established in all regional capital cities providing immediate shelter, counseling, legal aid and medical services.'],
    ['Forensic Laboratory Upgrade Enables DNA Testing for Criminal Cases','The government analytical laboratory has completed a major upgrade enabling DNA forensic testing capabilities that will significantly enhance criminal investigation success rates.'],
    ['Drug Enforcement Unit Seizes Record Narcotics Shipment at Entebbe','The Drug Enforcement Unit has made its largest ever seizure of narcotics at Entebbe International Airport, intercepting a shipment with an estimated street value of USD 5 million.'],
    ['Wildlife Crime Unit Arrests Major Ivory Trafficking Syndicate','A specialized wildlife crime unit has arrested members of a major ivory trafficking syndicate responsible for the illegal export of elephant tusks and pangolin scales.'],
    ['Fire Brigade Service Expansion Reduces Emergency Response Time by 50 Percent','The expansion of the fire brigade service with new stations and equipment has reduced average emergency response times from 45 minutes to under 20 minutes in urban areas.'],
    ['Witness Protection Programme Successfully Shields 200 Individuals','The Witness Protection Programme has successfully protected over 200 individuals whose testimony was crucial in securing convictions in major criminal cases over three years.'],
    ['Border Security Enhanced with Biometric Identification at All Entry Points','All major border entry points have been equipped with biometric identification systems capable of fingerprint and facial recognition to enhance security and immigration control.'],
],

'entertainment' => [
    ['Ugandan Film Industry Revenue Surpasses UGX 100 Billion Mark','The Ugandan film industry commonly known as Ugawood has surpassed the UGX 100 billion annual revenue mark driven by growing local audiences and increasing international distribution deals.'],
    ['National Theatre Reopens After Comprehensive Two Year Renovation','The Uganda National Theatre has reopened after a two-year comprehensive renovation that has modernized the facility while preserving its historical architectural character and heritage.'],
    ['Ugandan Musician Wins BET Award for Best African Act','A Ugandan musician has won the BET Award for Best African Act, becoming the first Ugandan artist to receive the prestigious international music recognition on the global stage.'],
    ['Kampala International Film Festival Draws Record Submissions','The Kampala International Film Festival has received a record number of film submissions from across Africa and the diaspora, establishing itself as a premier African film event.'],
    ['Traditional Dance Competition Attracts Performers from All Regions','The annual National Traditional Dance Competition has attracted performers representing all four regions of Uganda, showcasing the incredible diversity of Ugandan cultural heritage.'],
    ['Ugandan Fashion Designer Debuts Collection at Lagos Fashion Week','A rising Ugandan fashion designer has debuted a collection at Lagos Fashion Week featuring contemporary designs inspired by traditional Ugandan textiles, patterns and cultural motifs.'],
    ['Comedy Industry Boom Sees New Venues Opening Across Kampala','The booming Ugandan comedy industry has led to the opening of five new dedicated comedy venues across Kampala, with weekly shows consistently selling out to enthusiastic audiences.'],
    ['Music Streaming Reports Ugandan Artists Among Most Streamed in Africa','A major music streaming platform has revealed that Ugandan artists are among the most streamed in East Africa with Afrobeats, Kidandali and dancehall genres driving growth.'],
    ['National Book Festival Celebrates Ugandan Literature and Publishing','The annual National Book Festival has celebrated Ugandan literature with book launches, author readings and panel discussions featuring both established and emerging Ugandan writers.'],
    ['Ugandan Visual Artist Exhibition Opens at Prestigious Gallery','A Ugandan contemporary visual artist has opened a solo exhibition at a prestigious international gallery showcasing works exploring themes of identity, migration and African modernity.'],
    ['Gaming Industry Grows as Ugandan Developers Create Mobile Games','The gaming industry in Uganda is experiencing rapid growth with local developers creating mobile games that incorporate Ugandan themes, languages and cultural references for audiences.'],
    ['Nyege Nyege Festival Economic Impact Estimated at UGX 30 Billion','An economic impact study estimates that the annual Nyege Nyege Music Festival generates approximately UGX 30 billion for the local economy in Jinja and surrounding areas.'],
    ['Television Production Quality Rises as Local Series Gain Regional Audience','Ugandan television series production quality has improved significantly with several local productions gaining dedicated audiences across East Africa through streaming platforms.'],
    ['Art Auction Raises Record Amount for Youth Creative Arts Programme','A charity art auction has raised a record UGX 800 million for a programme supporting young Ugandans pursuing careers in visual arts, music, film and performing arts.'],
    ['Cultural Heritage Museum Opens Interactive Digital Exhibition Wing','The Uganda Museum has opened a new interactive digital exhibition wing using virtual reality and augmented reality technology to bring cultural heritage to life for visitors.'],
],

'education' => [
    ['National Education Policy Review Proposes Competency-Based Curriculum','A comprehensive review of the national education policy has recommended a shift to competency-based curriculum emphasizing practical skills, critical thinking and creativity.'],
    ['Makerere University Rises 50 Places in Global Rankings','Makerere University has risen 50 places in the latest global university rankings, credited to increased research output, international collaborations and improved student outcomes.'],
    ['Government Introduces Free Secondary Education in All Government Schools','The government has announced the extension of free education to cover all government-aided secondary schools, removing tuition fees that previously blocked many students.'],
    ['Technical and Vocational Education Enrollment Doubles Following Reform','Enrollment in technical and vocational education institutions has doubled over three years following reforms that improved equipment, curriculum quality and industry partnerships.'],
    ['National Examinations Board Reports Highest Pass Rates in PLE','The Uganda National Examinations Board has reported the highest ever pass rates in the Primary Leaving Examinations with a 92% pass rate across all regions of the country.'],
    ['School Feeding Programme Expands to Cover 5 Million Students','The national school feeding programme has expanded to cover 5 million students across all districts, providing nutritious midday meals that have improved attendance and learning.'],
    ['Teacher Training Colleges Adopt Technology-Enhanced Learning','All national teacher training colleges have adopted technology-enhanced learning methods including digital lesson planning, multimedia content creation and online assessment tools.'],
    ['Private University Launches East Africa First AI Degree Programme','A Kampala-based private university has launched East Africa first dedicated Bachelor of Science in Artificial Intelligence degree programme with enrollment exceeding all expectations.'],
    ['Student Loan Scheme Disburses UGX 200 Billion in First Year','The Higher Education Student Financing Board has disbursed UGX 200 billion in student loans during its first year, enabling 50,000 students to access university education.'],
    ['Early Childhood Education Centres Established in 100 Sub-Counties','The government has established early childhood education centres in 100 sub-counties targeting children aged 3-5 years with structured early learning programmes and trained educators.'],
    ['National Library Digital Transformation Puts 500000 Books Online','The National Library of Uganda has completed a digital transformation project putting over 500,000 books and documents online, accessible to students and researchers nationwide.'],
    ['STEM Education Programme Achieves Gender Parity in Participating Schools','A targeted STEM education programme has achieved gender parity in science and mathematics enrollment at participating schools through mentorship, scholarships and role modelling.'],
    ['Uganda Schools Win Regional Science and Innovation Competition','Ugandan students have won top prizes at the East African Regional Science and Innovation Competition with projects addressing local challenges in health, agriculture and environment.'],
    ['Special Needs Education Policy Ensures Inclusive Learning Environments','A new special needs education policy requires all schools to provide inclusive learning environments with trained teachers, assistive technologies and accessible physical facilities.'],
    ['Distance Learning Platform Reaches 100000 Students in Remote Areas','A government distance learning platform combining radio broadcasts, SMS-based learning and offline digital content has reached 100,000 students in hard-to-reach areas.'],
],

'environment' => [
    ['Uganda Tree Planting Campaign Achieves 50 Million Trees Milestone','The national tree planting campaign has achieved its target of 50 million trees planted across the country, contributing to restoration of degraded landscapes and climate change mitigation.'],
    ['Lake Victoria Water Quality Programme Shows Measurable Results','A comprehensive Lake Victoria water quality improvement programme has achieved measurable reductions in pollution through wetland restoration, waste management and industrial regulation.'],
    ['Mountain Gorilla Population Reaches Historic High of 1100','Conservation efforts in Bwindi and Mgahinga forests have helped push the mountain gorilla population to a historic high of 1,100 individuals according to the latest census data.'],
    ['Solar Energy Installations Surpass Hydropower Capacity for First Time','Uganda installed solar energy capacity has surpassed traditional hydropower for the first time, marking a significant shift in the country energy landscape toward renewables.'],
    ['Wetland Restoration Project Revives Critical Ecosystems Around Kampala','A major wetland restoration project has restored over 2,000 hectares of degraded wetlands around Kampala, improving flood control, water filtration and biodiversity.'],
    ['Community Forest Management Reduces Deforestation by 45 Percent','A community-based forest management programme has reduced deforestation rates by 45% by giving communities direct ownership and economic benefits from sustainable forest use.'],
    ['Electric Vehicle Pilot Programme Launches in Kampala with 200 Buses','Kampala has launched an electric vehicle pilot programme with 200 electric buses on major routes, reducing air pollution and demonstrating the viability of clean public transport.'],
    ['National Climate Adaptation Plan Prioritizes Agriculture and Water','Uganda new National Climate Change Adaptation Plan prioritizes agriculture and water sectors, allocating UGX 2 trillion for climate-resilient farming and water infrastructure.'],
    ['Plastic Ban Enforcement Yields 60 Percent Reduction in Single-Use Plastics','Two years after enacting a comprehensive plastic ban, enforcement efforts have yielded a 60% reduction in single-use plastic waste across major urban centres.'],
    ['Karamoja Reforestation Programme Combats Desertification','An ambitious reforestation programme in Karamoja is combating advancing desertification through drought-resistant tree planting, water harvesting and sustainable land management.'],
    ['Biodiversity Survey Discovers 30 New Species in Rwenzori Mountains','A comprehensive biodiversity survey of the Rwenzori Mountains has discovered 30 previously unknown species including insects, plants and amphibians unique to the ecosystem.'],
    ['Carbon Credit Trading Generates Revenue for Rural Communities','A carbon credit trading programme linked to reforestation and clean cookstove projects is generating significant revenue for rural communities while contributing to climate goals.'],
    ['Nile Basin Cooperation Strengthens Water Resource Management','East African nations sharing the Nile Basin have strengthened their cooperation agreement, establishing new frameworks for equitable and sustainable water resource sharing.'],
    ['Air Quality Monitoring Network Expands to 50 Stations Nationwide','The national air quality monitoring network has expanded to 50 stations providing real-time data on air pollution levels and enabling evidence-based policies for improvement.'],
    ['Wildlife Corridor Restoration Connects Three National Parks','A landmark wildlife corridor restoration project has reconnected three national parks, allowing elephants, lions and other large mammals to move freely between protected areas.'],
],

'agriculture' => [
    ['Uganda Launches National Coffee Replanting Programme Targeting 300 Million Trees','UCDA has launched an ambitious replanting programme targeting 300 million new coffee trees over five years to replace aging plantations and boost national production capacity.'],
    ['Irrigation Expansion Brings Year-Round Farming to 50000 Hectares','A major government irrigation expansion project has brought year-round farming capability to 50,000 hectares of previously rain-dependent agricultural land across Eastern and Northern Uganda.'],
    ['Fish Farming Revolution Produces 100000 Tonnes Annually','Uganda aquaculture sector has reached production of 100,000 tonnes annually, driven by improved cage farming techniques on Lake Victoria and pond farming in rural communities.'],
    ['Digital Agriculture Platform Serves One Million Smallholder Farmers','A comprehensive digital agriculture platform providing weather information, market prices, agronomic advice and financial services now serves over one million smallholder farmers.'],
    ['Organic Export Market Grows as Uganda Gains International Certifications','Uganda organic agricultural exports have grown significantly as producers gain international certifications, commanding premium prices in European and Asian markets.'],
    ['National Grain Reserve Reaches Target Capacity of 500000 Tonnes','The National Strategic Grain Reserve has reached its target capacity of 500,000 tonnes, providing food security buffer against potential harvest failures and regional food crises.'],
    ['Dairy Industry Modernization Doubles Milk Processing Capacity','The dairy industry modernization programme has doubled national milk processing capacity through new processing plants, cold chain infrastructure and quality improvement.'],
    ['Climate-Smart Agriculture Practices Adopted by 2 Million Farmers','Over 2 million Ugandan farmers have adopted climate-smart agricultural practices including conservation agriculture, agroforestry and drought-resistant crop varieties.'],
    ['Vanilla Production Recovery Positions Uganda as Top Global Exporter','Uganda vanilla production has recovered strongly following targeted support, positioning the country to challenge Madagascar as the world top vanilla exporting nation.'],
    ['Agricultural Cooperative Movement Reaches 15000 Registered Societies','The agricultural cooperative movement has reached 15,000 registered cooperative societies with combined membership exceeding 5 million farmers nationwide.'],
    ['Post-Harvest Loss Reduction Programme Saves 30 Percent of Crop Value','A nationwide post-harvest loss reduction programme providing hermetic storage solutions has saved an estimated 30% of crop value previously lost to spoilage and pests.'],
    ['Rice Production Self-Sufficiency Achieved Through Upland Varieties','Uganda has achieved rice production self-sufficiency through the widespread adoption of high-yielding upland rice varieties that do not require traditional irrigated paddies.'],
    ['Agricultural Extension Reform Reaches 90 Percent of Farming Households','The reformed agricultural extension service now reaches 90% of farming households through government workers, private advisors and digital advisory services.'],
    ['Horticulture Export Sector Targets USD 500 Million Annual Revenue','The horticulture export sector has set a target of USD 500 million in annual revenue driven by growing demand for Ugandan flowers, fruits and vegetables internationally.'],
    ['Land Consolidation Programme Improves Farm Productivity by 200 Percent','A voluntary land consolidation programme has demonstrated productivity improvements of up to 200% by enabling mechanized farming on larger consolidated plots.'],
],

'lifestyle' => [
    ['Kampala Restaurant Scene Evolves with Farm-to-Table Movement','Kampala dining scene is experiencing a revolution as a growing number of restaurants embrace the farm-to-table philosophy, sourcing ingredients directly from local organic farmers.'],
    ['Ugandan Coffee Culture Blooms with Specialty Cafes Across Cities','A vibrant specialty coffee culture is emerging in Uganda with artisanal cafes in Kampala, Jinja and Mbarara serving single-origin Ugandan coffee that rivals the best globally.'],
    ['Adventure Tourism Boom Puts Uganda on Global Travel Destination Map','Uganda adventure tourism sector is booming with white-water rafting, bungee jumping, mountain climbing and gorilla trekking attracting record numbers of international visitors.'],
    ['Fashion Industry Growth Creates Opportunities for Textile Designers','The growing Ugandan fashion industry is creating new opportunities for textile designers blending traditional Ugandan fabrics and patterns with contemporary fashion trends.'],
    ['Wellness Tourism Develops Around Natural Hot Springs','A new wellness tourism niche is developing around Uganda natural hot springs and traditional healing practices, attracting visitors seeking alternative health experiences.'],
    ['Urban Gardening Movement Transforms Kampala Rooftops','An urban gardening movement is transforming Kampala rooftops and balconies into productive green spaces where city residents grow vegetables, herbs and fruits.'],
    ['Weekend Market Culture Thrives with Artisan and Organic Markets','Weekend artisan and organic markets have become a thriving cultural phenomenon in Kampala with vendors selling handcrafted goods, organic produce and street food.'],
    ['Ugandan Wedding Industry Valued at UGX 500 Billion Annually','The Ugandan wedding industry has been valued at approximately UGX 500 billion annually encompassing venues, catering, fashion, photography and event planning services.'],
    ['Fitness Industry Grows as Gym Culture Takes Hold Among Youth','The fitness industry in Uganda is experiencing rapid growth as gym culture takes hold among young professionals with modern fitness centres opening across the country.'],
    ['Pet Ownership Rises in Urban Areas Creating New Business Opportunities','Pet ownership is rising significantly in Kampala and other urban areas, creating new business opportunities in veterinary services, pet food, accessories and grooming.'],
    ['Book Club Culture Flourishes with Over 200 Active Groups Nationwide','The book club culture in Uganda is flourishing with over 200 active reading groups meeting regularly, driven by a new generation of readers and local publishers.'],
    ['Electric Bicycle Tours Offer Eco-Friendly Way to Explore Kampala','Electric bicycle tour companies are offering eco-friendly guided tours through Kampala historic neighborhoods, markets and cultural sites for tourists and locals alike.'],
    ['Home Interior Design Industry Grows as Middle Class Expands','The home interior design industry is growing as Uganda expanding middle class invests in personalized living spaces, supporting local furniture makers and designers.'],
    ['Craft Beer Movement Introduces Unique Ugandan Flavours to Brewing','A craft beer movement is introducing unique Ugandan flavours with local microbreweries incorporating passion fruit, vanilla, coffee and jackfruit into their recipes.'],
    ['Digital Nomad Community Grows as Uganda Attracts Remote Workers','Uganda is attracting a growing community of digital nomads drawn by affordable living costs, reliable internet in urban areas and the country natural beauty.'],
],

'science' => [
    ['Makerere Researchers Develop Low-Cost Water Purification Technology','Researchers at Makerere University have developed an innovative low-cost water purification technology using locally available materials for clean drinking water in rural communities.'],
    ['Uganda National Science Academy Launches Research Grant Programme','The newly established Uganda National Science Academy has launched a competitive research grant programme with UGX 10 billion allocated to support scientific research.'],
    ['Ugandan Scientist Wins International Award for Malaria Research','A Ugandan scientist has won a prestigious international award for a breakthrough in malaria research identifying new drug targets for treatment-resistant malaria strains.'],
    ['Space Science Programme Trains First Cohort of Satellite Engineers','Uganda space science programme has graduated its first cohort of satellite engineers trained in satellite design, construction and operation with international space agencies.'],
    ['Archaeological Discovery in Karamoja Reveals 3000 Year Old Settlement','Archaeologists have discovered the remains of a 3,000-year-old settlement in Karamoja providing new insights into early agricultural practices of ancient East African societies.'],
    ['National Weather Service Deploys Advanced Climate Modelling System','The Uganda National Meteorological Authority has deployed an advanced climate modelling system capable of providing seasonal forecasts with significantly improved accuracy.'],
    ['Genetic Research Maps Indigenous Ugandan Crop Varieties','A comprehensive genetic research project has mapped the diversity of indigenous Ugandan crop varieties creating a genetic database essential for conservation and crop improvement.'],
    ['Renewable Energy Research Centre Develops Improved Solar Efficiency','The Uganda Renewable Energy Research Centre has developed a coating technology that improves solar panel efficiency by 20% under equatorial African sunlight conditions.'],
    ['Mathematical Sciences Institute Produces Record PhD Graduates','The African Institute for Mathematical Sciences Uganda campus has produced a record number of PhD graduates whose research addresses climate modelling and epidemiology.'],
    ['Paleontological Find Reveals New Primate Species in Western Uganda','Paleontologists working in Western Uganda have discovered fossilized remains of a previously unknown primate species dating back 15 million years in evolutionary history.'],
    ['National Research Ethics Framework Strengthens Scientific Integrity','Uganda has established a comprehensive national research ethics framework that strengthens scientific integrity while facilitating ethical research and clinical trials.'],
    ['Innovation in Banana Fibre Processing Creates Sustainable Textiles','Ugandan researchers have developed an innovative process for converting banana plant fibre into high-quality textile material offering a sustainable alternative to cotton.'],
    ['Lake Victoria Research Station Monitors Ecosystem with New Technology','The Lake Victoria Research Station has deployed new underwater monitoring technology providing real-time data on water quality, fish populations and invasive species.'],
    ['Nuclear Energy Feasibility Study Completed for Future Energy Mix','A comprehensive feasibility study on nuclear energy for Uganda has been completed assessing potential sites, regulatory requirements and economic viability for the energy mix.'],
    ['Citizen Science Programme Engages 10000 Volunteers in Biodiversity','A citizen science programme has engaged over 10,000 volunteers across Uganda in monitoring bird populations, butterfly species and plant diversity for national databases.'],
],

'religion' => [
    ['Inter-Faith Council Launches National Unity and Peace Campaign','The Uganda Inter-Religious Council has launched a nationwide campaign for unity and peace bringing together leaders from Christian, Muslim, Hindu and traditional African communities.'],
    ['Archbishop Leads Pilgrimage to Uganda Martyrs Shrine Drawing 3 Million','The annual pilgrimage to the Uganda Martyrs Shrine at Namugongo has drawn an estimated 3 million pilgrims from across Africa in one of the largest religious gatherings.'],
    ['Islamic University Expands Academic Programmes Beyond Religious Studies','The Islamic University in Uganda has expanded its programmes to include engineering, medicine and business administration while maintaining its Islamic studies foundation.'],
    ['Church Leaders Speak Out on Environmental Stewardship','Religious leaders across denominations have issued a joint statement calling for environmental stewardship and climate action, describing care for creation as a moral imperative.'],
    ['Faith-Based Organizations Contribute 30 Percent of Healthcare Delivery','A new study reveals that faith-based organizations contribute approximately 30% of all healthcare delivery in Uganda, operating hospitals and clinics across the country.'],
    ['Interfaith Dialogue Programme Reduces Religious Tensions in East','An interfaith dialogue programme implemented in the Eastern region has successfully reduced religious tensions through regular meetings and joint community projects.'],
    ['Gospel Music Industry Grows with Artists Gaining International Following','The Ugandan gospel music industry is experiencing significant growth with several artists gaining substantial international followings through streaming platforms.'],
    ['Religious Tourism Development Targets Historic Churches and Mosques','A religious tourism development programme is targeting historic churches, mosques and traditional spiritual sites as destinations for domestic and international visitors.'],
    ['Theological Seminary Opens Centre for Ethics in Public Life','A leading theological seminary has opened a Centre for Ethics in Public Life offering programmes in ethical leadership, governance and social justice for civic leaders.'],
    ['Faith Communities Lead Refugee Integration and Support Programmes','Faith communities across Uganda are leading efforts to integrate refugees, providing shelter, education, vocational training and psychosocial support services.'],
    ['Annual National Prayer Breakfast Addresses Youth Empowerment','The annual National Prayer Breakfast attended by government officials, religious leaders and diplomats has focused on the theme of youth empowerment and moral formation.'],
    ['Religious Schools Maintain Top Performance in National Examinations','Schools operated by religious organizations continue to maintain top performance in national examinations with several faith-based schools ranking among the best nationwide.'],
    ['Church Construction Boom Reflects Growing Religious Observance','A construction boom in places of worship reflects growing religious observance, with modern purpose-built facilities replacing temporary structures across the country.'],
    ['Religious Leaders Mediate Community Land Disputes Successfully','Religious leaders serving as mediators in community land disputes have achieved significantly higher settlement success rates than formal legal channels, especially in the North.'],
    ['Youth Ministry Programmes Engage Million Young People in Service','Youth ministry programmes across denominations have engaged over one million young Ugandans in community service projects ranging from environmental conservation to elderly care.'],
],

'investigations' => [
    ['Inside the Billion-Shilling Government Procurement Fraud Network','A months-long investigation has uncovered a sophisticated procurement fraud network operating across multiple government ministries, involving phantom companies and inflated contracts.'],
    ['Land Grabbing in Northern Uganda: The Untold Story of Displacement','This investigation reveals how powerful individuals have systematically acquired vast tracts of communal land in Northern Uganda, displacing thousands of families from IDP camps.'],
    ['The Hidden Cost of Illegal Mining in Karamoja Region','An extensive investigation into illegal mining operations in Karamoja reveals environmental devastation, exploitation of child labour and flow of illicit minerals internationally.'],
    ['How Fake Medicines Enter Uganda Health System','A comprehensive investigation traces counterfeit medicines from manufacturing facilities in Asia through corrupt customs officials to pharmacy shelves and hospitals across Uganda.'],
    ['The School Fees Scandal: Where Does Parents Money Go','This investigation examines how some private schools charge exorbitant fees while providing substandard education, with funds diverted to property investments and personal enrichment.'],
    ['Tracking Illegal Wildlife Trade From Uganda Parks to International Markets','An undercover investigation spanning three countries has documented the wildlife trafficking pipeline from Uganda national parks through middlemen to buyers in Asia.'],
    ['The Water Project That Never Was: Tracing Billions in Lost Funds','An investigation into rural water projects reveals billions allocated for borehole construction were siphoned through fake completion certificates and non-existent contractors.'],
    ['Tax Evasion by Multinational Corporations: Uganda Lost Revenue','This investigation reveals how multinational corporations use transfer pricing, profit shifting and treaty shopping to minimise tax obligations costing Uganda billions annually.'],
    ['The Refugee Numbers Game: Investigating Population Inflation Allegations','A detailed investigation examines allegations that refugee population figures have been inflated at some settlements, potentially diverting humanitarian aid from genuine needs.'],
    ['Inside the Illegal Timber Trade Devastating Uganda Natural Forests','An investigation has documented the systematic destruction of natural forests with timber smuggled across borders using forged permits and corrupt forestry officials.'],
    ['Ghost Workers on Government Payroll: A UGX 200 Billion Annual Drain','An investigation using data analysis has identified thousands of ghost workers on the government payroll, costing taxpayers an estimated UGX 200 billion annually.'],
    ['The Road Contracts That Crumble: Investigating Infrastructure Quality','This investigation examines why newly constructed roads deteriorate within months, revealing substandard materials, poor supervision and compromised quality assurance.'],
    ['Toxic Chemicals in Uganda Food Supply: An Unregulated Danger','An investigation reveals alarming levels of pesticide residues, heavy metals and banned chemicals in commonly consumed foods due to inadequate regulation and enforcement.'],
    ['The Student Loan Default Crisis: Where Are the Graduates','An investigation into the government student loan programme reveals alarmingly high default rates with significant challenges in tracking graduates and recovering repayments.'],
    ['How Political Financing Shapes Policy Decisions in Uganda','This investigation examines the relationship between political campaign financing and subsequent policy decisions, revealing patterns that raise questions about democratic governance.'],
],

'real-estate' => [
    ['Kampala Property Prices Rise 15 Percent as Housing Demand Surges','Property prices in Kampala have risen by an average of 15% over the past year driven by demand for modern housing from the growing middle class and returning diaspora.'],
    ['Government Launches Affordable Housing Programme for 100000 Units','The government has launched an ambitious affordable housing programme targeting 100,000 housing units over five years to address the growing urban housing deficit.'],
    ['Mixed-Use Development Boom Transforms Kampala Skyline','A boom in mixed-use developments combining residential, commercial and retail spaces is transforming the Kampala skyline with several projects exceeding 20 stories.'],
    ['Mortgage Market Grows 40 Percent as Banks Introduce Flexible Loans','The mortgage market has grown by 40% following introduction of flexible home loan products with longer terms, lower deposits and competitive interest rates.'],
    ['Smart City Masterplan for Entebbe Satellite Town Unveiled','A comprehensive smart city masterplan has been unveiled for a new satellite town near Entebbe incorporating smart infrastructure, green building and integrated transport.'],
    ['Gulu Real Estate Market Emerges as Northern Property Hub','The Gulu real estate market is experiencing rapid growth as city status and improved infrastructure attract property investors from Kampala and international markets.'],
    ['Green Building Standards Introduced for All New Commercial Construction','New green building standards require all new commercial construction to meet minimum requirements for energy efficiency, water conservation and sustainable materials.'],
    ['Real Estate Agents Licensing Framework Professionalizes Sector','A new licensing framework for real estate agents establishes qualification requirements, ethical standards and consumer protection mechanisms for the property sector.'],
    ['Industrial Park Development Creates New Commercial Property Opportunities','The development of industrial parks in Namanve, Mukono and Luzira is creating significant commercial property opportunities in warehousing, manufacturing and logistics.'],
    ['Condominium Living Gains Popularity Among Young Professionals','Condominium developments are gaining popularity among young urban professionals attracted by affordability, security, shared amenities and the community living experience.'],
    ['Heritage Building Preservation Programme Protects Kampala Architecture','A heritage building preservation programme has been launched to protect architecturally significant buildings in Kampala from demolition and inappropriate redevelopment.'],
    ['Rural Housing Programme Uses Local Materials and Traditional Design','An innovative rural housing programme uses locally available materials and traditional design principles to build affordable, comfortable and culturally appropriate homes.'],
    ['Property Tax Reform to Generate UGX 500 Billion Municipal Revenue','Proposed property tax reforms are expected to generate UGX 500 billion in additional municipal revenue through updated valuations, improved compliance and digital payments.'],
    ['Student Housing Market Grows Near Major University Campuses','The student housing market is experiencing growth near major university campuses with purpose-built accommodation offering modern amenities increasingly preferred over hostels.'],
    ['Land Title Digitization Completes 2 Million Title Conversions','The national land title digitization programme has completed 2 million paper-based title conversions to digital format, reducing fraud and speeding up property transactions.'],
],

];

// ── INSERT ───────────────────────────────────────────────────────
$sql = "INSERT INTO articles
    (title, slug, content, excerpt, author_id, category_id, featured_image,
     status, published_at, created_by, display_author, is_crawled, views)
    VALUES
    (:title, :slug, :content, :excerpt, :author, :cat, :img,
     'published', :pub, :created, 'Admin', false, :views)
    ON CONFLICT (slug) DO NOTHING";

$stmt = $pdo->prepare($sql);
$total = 0;
$baseTime = time();

foreach ($articles as $catSlug => $catArticles) {
    if (!isset($cats[$catSlug])) { echo "  SKIP: '{$catSlug}'\n"; continue; }
    $catId   = $cats[$catSlug]['id'];
    $catName = $cats[$catSlug]['name'];
    echo "{$catName}:\n";

    foreach ($catArticles as $i => [$title, $intro]) {
        $slug    = makeSlug($title);
        $content = buildContent($intro, $slug, $catName);
        $excerpt = mb_substr($intro, 0, 250);
        $featImg = img($slug, 1200, 630);
        $pubTime = date('Y-m-d H:i:s', $baseTime - ($total * rand(1200, 3600)));
        $views   = rand(50, 5000);

        try {
            $stmt->execute([
                ':title'   => $title,
                ':slug'    => $slug,
                ':content' => $content,
                ':excerpt' => $excerpt,
                ':author'  => $adminId,
                ':cat'     => $catId,
                ':img'     => $featImg,
                ':pub'     => $pubTime,
                ':created' => $adminId,
                ':views'   => $views,
            ]);
            $total++;
            echo "  + {$title}\n";
        } catch (\Throwable $e) {
            echo "  x " . substr($e->getMessage(), 0, 80) . "\n";
        }
    }
    echo "\n";
}

echo "=== SEEDED {$total} ARTICLES WITH IMAGES ACROSS " . count($articles) . " CATEGORIES ===\n";
echo "Featured images: picsum.photos/seed/{slug}/1200/630\n";
echo "In-article images: 3 per article at 800x450\n";