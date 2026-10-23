<?php

namespace Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Demo community for presentations: 300 members who use every text feature, plus 100 community posts.
// Every account uses an @plantifier.test email and the password below; delete those users to remove it all.
class DemoCommunitySeeder extends Seeder
{
    public const DOMAIN = 'plantifier.test';

    public const PASSWORD = 'Plantdemo2026';

    private const PLANTS = [
        ['Sampaguita', 'Jasminum sambac', 'Oleaceae', 'sun', 'moist', 'The national flower of the Philippines, with small white star-shaped blooms that are strongly fragrant at night.'],
        ['Gumamela', 'Hibiscus rosa-sinensis', 'Malvaceae', 'sun', 'moist', 'A hardy flowering shrub with large funnel-shaped flowers, common in Filipino gardens and fences.'],
        ['Kalamansi', 'Citrus × microcarpa', 'Rutaceae', 'sun', 'moist', 'A small citrus tree whose sour fruit is used in cooking and drinks.'],
        ['Malunggay', 'Moringa oleifera', 'Moringaceae', 'sun', 'dry', 'A fast-growing tree with small edible leaves rich in nutrients.'],
        ['Santan', 'Ixora coccinea', 'Rubiaceae', 'sun', 'moist', 'A shrub with dense clusters of small red, orange or yellow flowers that attract butterflies.'],
        ['Bougainvillea', 'Bougainvillea spectabilis', 'Nyctaginaceae', 'sun', 'dry', 'A thorny climbing shrub whose colourful papery bracts surround tiny white flowers.'],
        ['Snake plant', 'Dracaena trifasciata', 'Asparagaceae', 'shade', 'dry', 'An upright succulent with stiff sword-shaped leaves that tolerates low light and missed waterings.'],
        ['Monstera', 'Monstera deliciosa', 'Araceae', 'indirect', 'moist', 'A climbing aroid known for large leaves that develop splits and holes as they mature.'],
        ['Golden pothos', 'Epipremnum aureum', 'Araceae', 'indirect', 'moist', 'A trailing vine with heart-shaped, gold-streaked leaves; easy to propagate in water.'],
        ['Anthurium', 'Anthurium andraeanum', 'Araceae', 'indirect', 'moist', 'Glossy heart-shaped spathes in red or pink surround a slender flower spike.'],
        ['Moth orchid', 'Phalaenopsis amabilis', 'Orchidaceae', 'indirect', 'moist', 'An epiphytic orchid with long-lasting white flowers on arching spikes, native to the Philippines.'],
        ['Waling-waling', 'Vanda sanderiana', 'Orchidaceae', 'sun', 'moist', 'A prized native orchid from Mindanao with large, patterned flowers.'],
        ['Aloe vera', 'Aloe vera', 'Asphodelaceae', 'sun', 'dry', 'A succulent with thick, gel-filled leaves that grows well in pots.'],
        ['Lagundi', 'Vitex negundo', 'Lamiaceae', 'sun', 'dry', 'An aromatic shrub with five-fingered leaves, traditionally used in herbal preparations.'],
        ['Oregano', 'Coleus amboinicus', 'Lamiaceae', 'sun', 'dry', 'A fleshy, fuzzy-leaved herb with a strong scent, often grown in backyard pots.'],
        ['Tanglad', 'Cymbopogon citratus', 'Poaceae', 'sun', 'moist', 'Lemongrass: a clumping grass whose lemon-scented stalks flavour soups and tea.'],
        ['Pandan', 'Pandanus amaryllifolius', 'Pandanaceae', 'indirect', 'moist', 'A fragrant plant with long glossy leaves used to scent rice and desserts.'],
        ['Ylang-ylang', 'Cananga odorata', 'Annonaceae', 'sun', 'moist', 'A tree with drooping yellow-green flowers famous for their perfume.'],
        ['Narra', 'Pterocarpus indicus', 'Fabaceae', 'sun', 'moist', 'The national tree of the Philippines, with a wide crown and fragrant yellow blossoms.'],
        ['Banaba', 'Lagerstroemia speciosa', 'Lythraceae', 'sun', 'moist', 'A flowering tree with showy pink to purple blooms, common along roads and parks.'],
        ['Talisay', 'Terminalia catappa', 'Combretaceae', 'sun', 'moist', 'A coastal tree with layered branches and large leaves that turn red before falling.'],
        ['Kamias', 'Averrhoa bilimbi', 'Oxalidaceae', 'sun', 'moist', 'A small tree bearing very sour green fruits straight from the trunk.'],
        ['Siling labuyo', 'Capsicum frutescens', 'Solanaceae', 'sun', 'moist', 'A small, very hot native chili that grows easily in pots.'],
        ['Ampalaya', 'Momordica charantia', 'Cucurbitaceae', 'sun', 'moist', 'Bitter gourd: a climbing vine grown for its warty, bitter fruit.'],
        ['Fiddle-leaf fig', 'Ficus lyrata', 'Moraceae', 'indirect', 'moist', 'An indoor tree with large violin-shaped leaves that dislikes being moved.'],
        ['Rubber plant', 'Ficus elastica', 'Moraceae', 'indirect', 'moist', 'A sturdy houseplant with thick, glossy leaves and milky sap.'],
        ['Kalachuchi', 'Plumeria rubra', 'Apocynaceae', 'sun', 'dry', 'Frangipani: a small tree with thick branches and fragrant five-petalled flowers.'],
        ['Spider plant', 'Chlorophytum comosum', 'Asparagaceae', 'indirect', 'moist', 'A fountain of striped leaves that produces baby plantlets on long runners.'],
        ['ZZ plant', 'Zamioculcas zamiifolia', 'Araceae', 'shade', 'dry', 'A glossy, drought-tolerant houseplant that stores water in thick rhizomes.'],
        ['Peace lily', 'Spathiphyllum wallisii', 'Araceae', 'shade', 'moist', 'White hooded flowers over dark leaves; it droops visibly when thirsty.'],
        ['Mayana', 'Coleus scutellarioides', 'Lamiaceae', 'indirect', 'moist', 'Grown for its brightly patterned leaves in red, purple, green and yellow.'],
        ['Kangkong', 'Ipomoea aquatica', 'Convolvulaceae', 'sun', 'wet', 'Water spinach: a fast-growing leafy vegetable with hollow stems.'],
        ['Dama de noche', 'Cestrum nocturnum', 'Solanaceae', 'sun', 'moist', 'A shrub whose small tubular flowers release a strong scent after dark.'],
    ];

    private const TOXIC = ['Araceae' => 'Contains calcium oxalate crystals. Keep away from pets and children and do not eat.', 'Apocynaceae' => 'The milky sap can irritate skin. Do not eat any part.', 'Solanaceae' => 'Some parts may be toxic or irritating. Wash hands after handling.', 'Asparagaceae' => 'Mildly toxic to cats and dogs if chewed.', 'Moraceae' => 'The milky sap can irritate skin and is mildly toxic to pets.', 'Asphodelaceae' => 'The yellow latex under the skin is a strong laxative. Use only the clear gel.'];

    private const PLACES = [['Quezon City', 14.676, 121.0437], ['Manila', 14.5995, 120.9842], ['Makati', 14.5547, 121.0244], ['Pasig', 14.5764, 121.0851], ['Taguig', 14.5176, 121.0509], ['Antipolo', 14.6255, 121.1245], ['Baguio', 16.4023, 120.596], ['Tagaytay', 14.1153, 120.9621], ['Los Baños', 14.1699, 121.2441], ['Lipa', 13.9411, 121.1631], ['Batangas City', 13.7565, 121.0583], ['San Pablo', 14.0683, 121.3256], ['Naga', 13.6218, 123.1948], ['Legazpi', 13.1391, 123.7438], ['Iloilo City', 10.7202, 122.5621], ['Bacolod', 10.6765, 122.9509], ['Cebu City', 10.3157, 123.8854], ['Dumaguete', 9.3068, 123.3054], ['Tagbilaran', 9.65, 123.85], ['Tacloban', 11.2433, 125.0048], ['Davao City', 7.1907, 125.4553], ['Cagayan de Oro', 8.4542, 124.6319], ['General Santos', 6.1164, 125.1716], ['Zamboanga City', 6.9214, 122.079], ['Puerto Princesa', 9.7392, 118.7353], ['Vigan', 17.5747, 120.3869], ['San Fernando', 15.0286, 120.6898], ['Angeles', 15.145, 120.5887], ['Malolos', 14.8527, 120.816], ['Dagupan', 16.0433, 120.3333]];

    private const FIRST = ['Juan', 'Maria', 'Jose', 'Ana', 'Mark', 'Angelica', 'John Paul', 'Kristine', 'Carlo', 'Patricia', 'Miguel', 'Bea', 'Rafael', 'Camille', 'Paolo', 'Nicole', 'Gabriel', 'Jasmine', 'Joshua', 'Trisha', 'Christian', 'Andrea', 'Jerome', 'Mae', 'Kevin', 'Joanna', 'Ramon', 'Liza', 'Francis', 'Grace', 'Nathaniel', 'Hazel', 'Vincent', 'Rica', 'Emmanuel', 'Divine', 'Adrian', 'Shiela', 'Lorenzo', 'Erika', 'Ronaldo', 'Marites', 'Dennis', 'Cherry', 'Rodel', 'Jenny', 'Alvin', 'Leah', 'Bryan', 'Princess', 'Arnel', 'Rowena', 'Jericho', 'Janine', 'Noel', 'Aileen', 'Ian', 'Bianca', 'Enzo', 'Faith'];

    private const LAST = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Andrada', 'Castillo', 'Flores', 'Villanueva', 'Ramos', 'Castro', 'Rivera', 'Aquino', 'Navarro', 'Salazar', 'Mercado', 'Aguilar', 'Dela Cruz', 'De Leon', 'Pascual', 'Domingo', 'Gonzales', 'Lopez', 'Fernandez', 'Manalo', 'Soriano', 'Dizon', 'Valdez', 'Santiago', 'Gutierrez', 'Morales', 'Lim', 'Tan', 'Go', 'Sy', 'Villareal', 'Alcantara', 'Panganiban', 'Magbanua', 'Dimaculangan', 'Macaraeg', 'Bernardo', 'Estrada', 'Lacson', 'Marquez', 'Padilla', 'Roxas', 'Samonte', 'Tolentino', 'Umali', 'Vergara', 'Yap', 'Zamora', 'Belmonte', 'Cabrera', 'Delos Santos'];

    private const INTERESTS = ['Houseplants', 'Native trees', 'Herbs', 'Orchids', 'Edible gardens', 'Medicinal plants', 'Succulents', 'Flowering plants', 'Urban gardening', 'Conservation', 'Plant propagation', 'Wildflowers'];

    private array $plantIds = [];

    public function run(): void
    {
        if (DB::table('users')->where('email', 'like', '%@'.self::DOMAIN)->exists()) {
            $this->command?->warn('Demo community already exists (users @'.self::DOMAIN.'). Nothing was added.');

            return;
        }
        mt_srand(2026);
        DB::transaction(function () {
            $users = $this->users();
            $this->relationships($users);
            $this->gardens($users);
            $posts = $this->community($users);
            $this->messages($users);
            $this->conversations($users);
            $this->reports($users, $posts);
        });
        $this->command?->info('Demo community ready: 300 members @'.self::DOMAIN.' (password '.self::PASSWORD.') and 100 community posts.');
    }

    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function between(CarbonInterface $from, CarbonInterface $to): CarbonInterface
    {
        return $from->copy()->addSeconds(mt_rand(0, max(1, $to->getTimestamp() - $from->getTimestamp())));
    }

    /** @return array<int, array{id: int, name: string, joined: Carbon, place: array}> */
    private function users(): array
    {
        $password = Hash::make(self::PASSWORD);
        $bios = ['Weekend gardener growing herbs on a small balcony.', 'Plant parent of {n} pots and counting.', 'Learning the names of the trees on my street.', 'Collecting native orchids, slowly and legally.', 'Biology student who loves field trips.', 'Growing my own kalamansi and siling labuyo.', 'Teacher and backyard vegetable grower.', 'Into propagation: every cutting is a new friend.', 'Retired and finally have time for the garden.', 'Documenting plants around {place}.', 'Mostly succulents, a few stubborn ferns.', 'Rescuing sad plants from the hardware store.'];
        $users = [];
        $usernames = [];
        for ($i = 0; $i < 300; $i++) {
            $first = $this->pick(self::FIRST);
            $last = $this->pick(self::LAST);
            $place = $this->pick(self::PLACES);
            $base = Str::of($first.' '.$last)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->limit(20, '')->trim('_')->toString();
            do {
                $username = $base.'_'.mt_rand(1, 999);
            } while (isset($usernames[$username]));
            $usernames[$username] = true;
            $joined = now()->subDays(mt_rand(5, 120))->subMinutes(mt_rand(0, 1440));
            $interests = array_values(array_unique(array_map(fn () => $this->pick(self::INTERESTS), range(1, mt_rand(2, 4)))));
            $id = DB::table('users')->insertGetId([
                'name' => $first.' '.$last, 'email' => $username.'@'.self::DOMAIN, 'username' => $username, 'password' => $password,
                'email_verified_at' => $joined, 'onboarded_at' => $joined, 'local_password_set_at' => $joined,
                'bio' => str_replace(['{n}', '{place}'], [mt_rand(6, 60), $place[0]], $this->pick($bios)), 'municipality' => $place[0],
                'interests' => json_encode($interests), 'purpose' => $this->pick(['discover', 'grow', 'research', 'connect']), 'experience' => $this->pick(['beginner', 'beginner', 'enthusiast', 'enthusiast', 'experienced']),
                'discovery_enabled' => mt_rand(1, 10) > 1, 'friend_request_privacy' => $this->pick(['everyone', 'everyone', 'everyone', 'friends-of-friends']),
                'profile_visibility' => mt_rand(1, 10) > 2 ? 'everyone' : 'friends', 'connections_visibility' => 'everyone', 'activity_visibility' => mt_rand(1, 10) > 2 ? 'everyone' : 'friends',
                'created_at' => $joined, 'updated_at' => $joined,
            ]);
            $users[] = ['id' => $id, 'name' => $first.' '.$last, 'joined' => $joined, 'place' => $place];
        }

        return $users;
    }

    private function relationships(array $users): void
    {
        $rows = [];
        $seen = [];
        foreach ($users as $index => $user) {
            for ($n = mt_rand(3, 9); $n > 0; $n--) {
                $other = $users[mt_rand(0, count($users) - 1)];
                $pair = min($user['id'], $other['id']).':'.max($user['id'], $other['id']);
                if ($other['id'] === $user['id'] || isset($seen['friend'.$pair])) {
                    continue;
                }
                $seen['friend'.$pair] = true;
                $at = $this->between(max($user['joined'], $other['joined']), now());
                $rows[] = ['user_id' => $user['id'], 'target_id' => $other['id'], 'kind' => 'friend', 'status' => mt_rand(1, 10) > 2 ? 'accepted' : 'pending', 'created_at' => $at, 'updated_at' => $at];
            }
            for ($n = mt_rand(2, 8); $n > 0; $n--) {
                $other = $users[mt_rand(0, count($users) - 1)];
                if ($other['id'] === $user['id'] || isset($seen['follow'.$user['id'].':'.$other['id']])) {
                    continue;
                }
                $seen['follow'.$user['id'].':'.$other['id']] = true;
                $at = $this->between(max($user['joined'], $other['joined']), now());
                $rows[] = ['user_id' => $user['id'], 'target_id' => $other['id'], 'kind' => 'follow', 'status' => 'accepted', 'created_at' => $at, 'updated_at' => $at];
            }
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('relationships')->insertOrIgnore($chunk);
        }
    }

    private function gardens(array $users): void
    {
        $light = ['sun' => 'Give it at least 6 hours of direct sun.', 'indirect' => 'Keep it in bright, indirect light away from harsh afternoon sun.', 'shade' => 'It tolerates low light but grows faster in bright, indirect light.'];
        $water = ['moist' => 'Water when the top 2 cm of soil feels dry; do not let it sit in water.', 'dry' => 'Let the soil dry out between waterings; overwatering causes root rot.', 'wet' => 'Keep the soil constantly moist or grow it in shallow water.'];
        $observations = ['New leaf unfurled this week.', 'Repotted into a bigger pot with fresh soil.', 'Flower buds starting to form.', 'Moved closer to the window and it perked up.', 'Noticed a few yellow lower leaves; cut back on watering.', 'Took two cuttings for propagation.', 'First bloom of the season!', 'Treated a small mealybug spot with soapy water.', 'Growth slowed during the rainy days.', 'Fertilized with diluted compost tea.'];
        $noteTitles = ['Watering schedule', 'Repotting plan', 'Seed starting log', 'Pest watch', 'Garden wishlist', 'Field trip notes', 'Propagation experiments', 'Soil mix recipe'];
        $noteBodies = ['Monday and Thursday mornings for the outdoor pots. Indoor plants only when the soil is dry.', 'Mix: 2 parts garden soil, 1 part rice hull, 1 part compost. Works well for most of my pots.', 'Started seeds in recycled cups. Germination took about a week in the warm corner.', 'Checked under the leaves for mealybugs and aphids. Wiped them off and isolated the plant.', 'Next to find: a waling-waling (from a licensed grower only), a variegated pothos and a dwarf kalamansi.', 'Saw tall narra trees and blooming banaba along the road. Took photos of the bark and leaves.', 'Pothos cuttings rooted in water after 12 days. Monstera node took almost a month.', 'Rainy season reminder: raise pots off the ground so they drain properly.'];
        $notes = $pins = $observationRows = [];
        foreach ($users as $user) {
            for ($n = mt_rand(1, 5); $n > 0; $n--) {
                [$common, $scientific, $family, $sun, $moisture, $description] = $this->pick(self::PLANTS);
                $at = $this->between($user['joined'], now());
                $id = DB::table('plant_records')->insertGetId([
                    'user_id' => $user['id'], 'common_name' => $common, 'scientific_name' => $scientific, 'family' => $family, 'description' => $description,
                    'care' => $light[$sun].' '.$water[$moisture], 'safety' => self::TOXIC[$family] ?? 'No common toxicity concerns are known, but do not eat a plant identified only from a photo.',
                    'relationship' => $this->pick(['growing', 'growing', 'discovered', 'researching', 'wishlist']), 'favorite' => mt_rand(1, 10) > 7, 'created_at' => $at, 'updated_at' => $at,
                ]);
                $this->plantIds[$user['id']][] = [$id, $common];
                for ($o = mt_rand(0, 3); $o > 0; $o--) {
                    $day = $this->between($at, now());
                    $observationRows[] = ['plant_record_id' => $id, 'observed_on' => $day->toDateString(), 'body' => $this->pick($observations), 'height_cm' => mt_rand(0, 2) ? mt_rand(80, 1500) / 10 : null, 'created_at' => $day, 'updated_at' => $day];
                }
                if (mt_rand(1, 10) <= 4) {
                    $day = $this->between($at, now());
                    $visibility = $this->pick(['private', 'private', 'public_approximate', 'public_approximate', 'public_exact']);
                    $pins[] = ['user_id' => $user['id'], 'plant_record_id' => $id, 'title' => $this->pick(['Backyard', 'Balcony garden', 'Neighbourhood park', 'School garden', 'Roadside', 'Community garden', 'Plaza', 'Riverbank trail']).' '.strtolower($common),
                        'area' => $user['place'][0], 'description' => $this->pick(['Healthy and flowering.', 'Several plants growing together.', 'Spotted on a morning walk.', 'Planted here last year.', null]),
                        'latitude' => round($user['place'][1] + mt_rand(-300, 300) / 10000, 7), 'longitude' => round($user['place'][2] + mt_rand(-300, 300) / 10000, 7),
                        'visibility' => $visibility, 'sensitive' => $visibility !== 'private' && mt_rand(1, 10) === 1, 'observed_on' => $day->toDateString(), 'created_at' => $day, 'updated_at' => $day];
                }
            }
            for ($n = mt_rand(0, 3); $n > 0; $n--) {
                $at = $this->between($user['joined'], now());
                $plant = mt_rand(0, 1) ? $this->pick($this->plantIds[$user['id']])[0] : null;
                $notes[] = ['user_id' => $user['id'], 'plant_record_id' => $plant, 'title' => $this->pick($noteTitles), 'body' => $this->pick($noteBodies), 'revision' => 1, 'created_at' => $at, 'updated_at' => $at];
            }
        }
        foreach (['observations' => $observationRows, 'notes' => $notes, 'map_pins' => $pins] as $table => $rows) {
            foreach (array_chunk($rows, 300) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }

    /** @return int[] post ids */
    private function community(array $users): array
    {
        $templates = [
            'question' => ['Why are the leaves on my {plant} turning yellow? It sits near a window and I water it twice a week.', 'Is it normal for my {plant} to drop leaves after repotting? It was fine before I moved it.', 'Any tips for getting a {plant} to bloom? Mine has been healthy for months but no flowers yet.', 'What is the best soil mix for {plant} here in {area}? The rainy season keeps my pots soggy.', 'My {plant} has small white fluffy spots under the leaves. Are these mealybugs? How do I treat them safely?', 'How often should I fertilize a {plant}? I have compost and some slow-release pellets.', 'Can a {plant} survive indoors with only a north-facing window?'],
            'care' => ['Care note for {plant}: water early in the morning so the leaves dry before evening. It cut down fungal spots on mine.', 'If your {plant} looks leggy, it wants more light. Move it gradually so the leaves do not scorch.', 'Rainy season reminder: raise your {plant} pots off the ground and clear the drainage holes.', 'For {plant}, I mix rice hull into the soil. Drainage improved a lot and the roots look healthier.', 'Wipe the dust off {plant} leaves with a damp cloth every few weeks. They photosynthesize better and pests are easier to spot.'],
            'progress' => ['Week {n} with my {plant}: three new leaves and the first aerial root. Slow but steady!', 'Before and after: my {plant} was almost dead when I got it. {n} weeks later it is pushing new growth.', 'My {plant} cutting finally rooted after {n} days in water. Potting it up this weekend.', 'Update on the {plant} I rescued: it flowered for the first time today.', 'Month {n} of the balcony garden. The {plant} is now taller than the railing.'],
            'tips' => ['Tip: propagate {plant} from cuttings with at least one node. Change the water every few days.', 'Use rice-washing water for your {plant}. Let it cool and use it within the day.', 'Quick pest fix: a few drops of dish soap in a liter of water, sprayed under the leaves of your {plant}. Test on one leaf first.', 'Group humidity-loving plants like {plant} together. They create a small humid zone around each other.', 'Do not water on a fixed schedule. Check the soil of your {plant} with your finger first.'],
            'post' => ['Spotted a beautiful {plant} blooming near {area} today. The scent was amazing!', 'Our school garden finally has a {plant}. The students named it already.', 'Found a huge {plant} on a morning walk around {area}. Must be decades old.', 'Weekend project: built a small shelf for my {plant} collection.', 'Swapped cuttings with a neighbour today. Brought home a {plant}!', 'Rain all week and my {plant} is loving it.'],
        ];
        $commentsLibrary = ['Same thing happened to mine! Less water fixed it.', 'Beautiful! How long have you had it?', 'Try moving it to a brighter spot, but not direct afternoon sun.', 'Thanks for sharing this, very helpful.', 'That looks so healthy. What fertilizer do you use?', 'I had mealybugs too. Neem oil worked for me.', 'Check the roots. If they are brown and mushy, it might be root rot.', 'Wow, I need one of these for my garden.', 'Saving this tip for later!', 'Mine took about a month to recover after repotting. Be patient.', 'Is that near the plaza? I think I have seen it too.', 'Rice hull really helps drainage, I agree.', 'Congrats on the first bloom!', 'Where did you buy yours? I am looking for one.', 'Great progress! Keep us updated.', 'Yellow lower leaves are usually normal as the plant grows new ones.'];
        $replies = ['Thank you! I will try that.', 'Good point, I had not thought of that.', 'About three months now.', 'Just compost tea every two weeks.', 'Yes, near the old church.', 'Will do!', 'That makes sense, thanks.'];
        $postIds = [];
        $reactionRows = [];
        $voters = array_column($users, 'id');
        foreach (range(1, 100) as $index) {
            $author = $users[mt_rand(0, count($users) - 1)];
            $kind = $this->pick(['question', 'question', 'care', 'progress', 'progress', 'tips', 'post', 'post']);
            $plant = $this->pick($this->plantIds[$author['id']] ?? [[0, 'gumamela']])[1];
            $at = $this->between(max($author['joined'], now()->subDays(45)), now()->subHours(2));
            $body = str_replace(['{plant}', '{area}', '{n}'], [strtolower($plant), $author['place'][0], mt_rand(2, 12)], $this->pick($templates[$kind]));
            $postId = DB::table('posts')->insertGetId(['user_id' => $author['id'], 'body' => $body, 'kind' => $kind, 'status' => 'published', 'area' => mt_rand(0, 1) ? $author['place'][0] : null, 'created_at' => $at, 'updated_at' => $at]);
            $postIds[] = $postId;
            $this->react($reactionRows, 'post', $postId, $voters, $author['id'], mt_rand(0, 25));
            for ($c = mt_rand(0, 7); $c > 0; $c--) {
                $commenter = $users[mt_rand(0, count($users) - 1)];
                $commentAt = $this->between($at, now());
                $commentId = DB::table('comments')->insertGetId(['post_id' => $postId, 'user_id' => $commenter['id'], 'body' => $this->pick($commentsLibrary), 'created_at' => $commentAt, 'updated_at' => $commentAt]);
                $this->react($reactionRows, 'comment', $commentId, $voters, $commenter['id'], mt_rand(0, 6));
                if (mt_rand(1, 10) <= 3) {
                    $replyAt = $this->between($commentAt, now());
                    DB::table('comments')->insert(['post_id' => $postId, 'user_id' => mt_rand(0, 1) ? $author['id'] : $users[mt_rand(0, count($users) - 1)]['id'], 'parent_id' => $commentId, 'body' => $this->pick($replies), 'created_at' => $replyAt, 'updated_at' => $replyAt]);
                }
            }
        }
        foreach (array_chunk($reactionRows, 300) as $chunk) {
            DB::table('social_reactions')->insertOrIgnore($chunk);
        }

        return $postIds;
    }

    private function react(array &$rows, string $type, int $target, array $voters, int $author, int $count): void
    {
        if ($count === 0) {
            return;
        }
        // array_rand returns a single key (not an array) when asked for one.
        foreach ((array) array_rand(array_flip($voters), min($count, count($voters))) as $voter) {
            if ($voter === $author) {
                continue;
            }
            $at = now()->subMinutes(mt_rand(1, 60 * 24 * 30));
            $rows[] = ['user_id' => $voter, 'target_type' => $type, 'target_id' => $target, 'reaction' => $this->pick(['loved', 'loved', 'fascinating', 'crazy']), 'created_at' => $at, 'updated_at' => $at];
        }
    }

    private function messages(array $users): void
    {
        $friends = DB::table('relationships')->where('kind', 'friend')->where('status', 'accepted')->whereIn('user_id', array_column($users, 'id'))->inRandomOrder()->limit(160)->get();
        $lines = ['Hi! Saw your post about the {plant}. How is it doing now?', 'It is doing great, thanks for asking!', 'Do you have extra cuttings? I would love to trade.', 'Sure, I can bring some on Saturday.', 'Where do you buy your potting mix?', 'There is a garden shop near the market, cheaper than the mall.', 'Thanks for the tip about watering less, it worked!', 'Glad it helped. Send a photo when it blooms.', 'Are you joining the tree planting next week?', 'Yes! See you there.'];
        foreach ($friends as $friend) {
            $start = Carbon::parse($friend->created_at);
            $thread = DB::table('threads')->insertGetId(['first_user_id' => min($friend->user_id, $friend->target_id), 'second_user_id' => max($friend->user_id, $friend->target_id), 'created_at' => $start, 'updated_at' => $start]);
            $plant = strtolower($this->pick(self::PLANTS)[0]);
            $at = $start->copy();
            $rows = [];
            for ($m = 0, $count = mt_rand(2, 6); $m < $count; $m++) {
                $at = $this->between($at, now());
                $rows[] = ['thread_id' => $thread, 'user_id' => $m % 2 ? $friend->target_id : $friend->user_id, 'body' => str_replace('{plant}', $plant, $lines[($m + mt_rand(0, 4) * 2) % count($lines)]), 'read_at' => $m < $count - 1 || mt_rand(0, 1) ? $at : null, 'created_at' => $at, 'updated_at' => $at];
            }
            DB::table('direct_messages')->insert($rows);
            DB::table('threads')->where('id', $thread)->update(['updated_at' => $at]);
        }
    }

    private function conversations(array $users): void
    {
        $qa = [
            ['How much sunlight does a {plant} need?', 'A {plant} generally grows best with the light it gets in its natural habitat. Start with bright, indirect light and watch the leaves: pale or stretched growth means it wants more light, while scorched brown patches mean it is getting too much direct sun.'],
            ['Why are the tips of my {plant} leaves turning brown?', 'Brown tips usually point to watering stress or dry air. Check that the pot drains well, water thoroughly only when the top of the soil is dry, and avoid placing it near fans or air conditioning. Trim the brown tips with clean scissors if you like; they will not turn green again.'],
            ['Is {plant} safe to keep around my cat?', 'Some ornamental plants are irritating or toxic to pets, so check the species before assuming it is safe. If your cat chews the leaves and drools, vomits or seems unwell, contact a veterinarian. Placing the plant out of reach is the simplest precaution.'],
            ['How do I propagate a {plant}?', 'Many plants like {plant} can be propagated from stem cuttings. Cut just below a node with clean shears, remove the lower leaves and root it in water or moist soil in bright, indirect light. Roots usually appear in two to six weeks depending on the plant and the temperature.'],
        ];
        $rows = [];
        foreach ($users as $user) {
            if (mt_rand(1, 10) > 4) {
                continue;
            }
            $plant = strtolower($this->pick($this->plantIds[$user['id']] ?? [[0, 'gumamela']])[1]);
            [$question, $answer] = $this->pick($qa);
            $at = $this->between($user['joined'], now());
            $conversation = DB::table('conversations')->insertGetId(['user_id' => $user['id'], 'title' => mb_substr(ucfirst(str_replace('{plant}', $plant, $question)), 0, 65), 'created_at' => $at, 'updated_at' => $at->copy()->addMinute()]);
            $style = $this->pick(['friendly', 'friendly', 'simple', 'formal', 'scientific']);
            $rows[] = ['conversation_id' => $conversation, 'role' => 'user', 'body' => str_replace('{plant}', $plant, $question), 'status' => 'complete', 'response_style' => $style, 'created_at' => $at, 'updated_at' => $at];
            $rows[] = ['conversation_id' => $conversation, 'role' => 'assistant', 'body' => str_replace('{plant}', $plant, $answer), 'status' => 'complete', 'response_style' => 'friendly', 'created_at' => $at->copy()->addSeconds(4), 'updated_at' => $at->copy()->addSeconds(4)];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('chat_messages')->insert($chunk);
        }
    }

    private function reports(array $users, array $posts): void
    {
        $reasons = ['This looks like spam advertising plants for sale.', 'The care advice here could be harmful to pets.', 'Off-topic post, not about plants.', 'This comment is rude to the original poster.', 'Possible location of a protected orchid species shared publicly.', 'Repeated post, already shared yesterday.'];
        foreach (range(1, 8) as $index) {
            $reporter = $users[mt_rand(0, count($users) - 1)];
            $post = $this->pick($posts);
            $comment = DB::table('comments')->where('post_id', $post)->value('id');
            [$type, $subject] = $comment && mt_rand(0, 1) ? ['comment', $comment] : ['post', $post];
            $at = now()->subHours(mt_rand(1, 96));
            DB::table('reports')->insert(['user_id' => $reporter['id'], 'subject_type' => $type, 'subject_id' => $subject, 'reason' => $this->pick($reasons), 'status' => 'pending', 'created_at' => $at, 'updated_at' => $at]);
        }
    }
}
