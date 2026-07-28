<?php

namespace App\Services\Upstream;

/**
 * Tells a PROFILE/PAGE link apart from a POST/VIDEO link, so an order isn't
 * accepted against the wrong kind of target.
 *
 * Followers land on a page; likes and views land on a specific post. Sending a
 * post URL to a followers service (or a bare profile to a likes service) means
 * the upstream either rejects it or silently delivers nothing — and the
 * customer has already paid. Real example we hit: someone ordering 3,000
 * Facebook followers pasted
 * "facebook.com/61592099696629/posts/1220974651774…" and it was accepted.
 */
class LinkTarget
{
    /** URL shapes that identify a single post / photo / video rather than a page. */
    private const POST_MARKERS = [
        '/posts/', '/photo', '/videos/', '/video/', '/reel/', '/reels/',
        '/p/', '/status/', 'story_fbid', 'permalink', 'watch?v=', 'youtu.be/',
    ];

    /** Service kinds that must point at a PROFILE or PAGE. */
    private const WANTS_PROFILE = ['follower', 'subscriber', 'member', 'connection'];

    /** Service kinds that must point at a specific POST / VIDEO. */
    private const WANTS_POST = ['like', 'view', 'comment', 'share', 'save', 'retweet'];

    /** 'post' when the URL names a single item, otherwise 'profile'. */
    public static function kindOf(string $link): string
    {
        $l = mb_strtolower(trim($link));

        foreach (self::POST_MARKERS as $marker) {
            if (str_contains($l, $marker)) {
                return 'post';
            }
        }

        return 'profile';
    }

    /** What this service needs its link to point at, or null when unknown. */
    public static function wantedFor(string $serviceName, string $serviceType = ''): ?string
    {
        $haystack = mb_strtolower($serviceType.' '.$serviceName);

        foreach (self::WANTS_PROFILE as $word) {
            if (str_contains($haystack, $word)) {
                return 'profile';
            }
        }
        foreach (self::WANTS_POST as $word) {
            if (str_contains($haystack, $word)) {
                return 'post';
            }
        }

        return null;
    }

    /**
     * Recover the PROFILE/PAGE url from a post url, when the post url carries
     * the owner in it — which is usually does.
     *
     * Getting a page link out of the Facebook app is genuinely fiddly, and
     * people paste whatever they can share. "facebook.com/6159.../posts/1220..."
     * already names the page, so deriving it beats sending them back to hunt
     * for Share → Copy Link.
     *
     * Returns null when the url simply doesn't identify the owner (an
     * "instagram.com/p/CODE" or "youtube.com/watch?v=" names only the post).
     */
    public static function toProfile(string $link): ?string
    {
        $link = trim($link);
        if ($link === '') {
            return null;
        }

        $host = mb_strtolower((string) parse_url($link, PHP_URL_HOST));
        $path = trim((string) parse_url($link, PHP_URL_PATH), '/');
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        // facebook.com/permalink.php?story_fbid=..&id=<pageId>
        if (str_contains($host, 'facebook.') && ! empty($query['id']) && ctype_digit((string) $query['id'])) {
            return 'https://www.facebook.com/profile.php?id='.$query['id'];
        }

        $segments = $path === '' ? [] : explode('/', $path);
        if ($segments === []) {
            return null;
        }

        // Everything before the post marker is the owner:
        //   <owner>/posts/123, <owner>/videos/123, @user/video/123, user/status/1
        $ownerParts = [];
        foreach ($segments as $segment) {
            if (in_array(mb_strtolower($segment), ['posts', 'post', 'photos', 'photo', 'videos', 'video', 'reel', 'reels', 'status', 'p'], true)) {
                break;
            }
            $ownerParts[] = $segment;
        }

        // Nothing before the marker → the url names only the item, not its owner.
        if ($ownerParts === [] || count($ownerParts) === count($segments)) {
            return null;
        }

        $owner = implode('/', $ownerParts);

        // A bare numeric Facebook owner is a page id, not a vanity slug.
        if (str_contains($host, 'facebook.') && ctype_digit($owner)) {
            return 'https://www.facebook.com/profile.php?id='.$owner;
        }

        $scheme = str_starts_with($link, 'http://') ? 'http' : 'https';

        return $scheme.'://'.$host.'/'.$owner;
    }

    /**
     * A concrete example of the link we're asking for, per platform and target.
     * "Send the link" means nothing to someone who has never copied one — an
     * example is the difference between a paste and a twenty-minute struggle.
     */
    private const EXAMPLES = [
        'facebook' => ['profile' => 'facebook.com/yourpagename', 'post' => 'facebook.com/yourpagename/posts/123456'],
        'instagram' => ['profile' => 'instagram.com/yourname', 'post' => 'instagram.com/p/Cx1y2z3'],
        'tiktok' => ['profile' => 'tiktok.com/@yourname', 'post' => 'tiktok.com/@yourname/video/7300000000'],
        'youtube' => ['profile' => 'youtube.com/@yourchannel', 'post' => 'youtube.com/watch?v=abc123'],
        'twitter' => ['profile' => 'x.com/yourname', 'post' => 'x.com/yourname/status/123456'],
        'x' => ['profile' => 'x.com/yourname', 'post' => 'x.com/yourname/status/123456'],
        'telegram' => ['profile' => 't.me/yourchannel', 'post' => 't.me/yourchannel/42'],
    ];

    /**
     * An example link for this service, e.g. "tiktok.com/@yourname". Falls back
     * to a generic shape so we ALWAYS have something concrete to show.
     */
    public static function exampleFor(string $platform, string $serviceName, string $serviceType = ''): string
    {
        $wanted = self::wantedFor($serviceName, $serviceType) ?? 'profile';
        $set = self::EXAMPLES[mb_strtolower(trim($platform))] ?? null;

        if ($set === null) {
            return $wanted === 'post' ? 'the link to your post/video' : 'the link to your page/profile';
        }

        return $set[$wanted];
    }

    /** Where a bare handle lives, per platform. */
    private const HANDLE_BASE = [
        'facebook' => 'https://www.facebook.com/',
        'instagram' => 'https://www.instagram.com/',
        'tiktok' => 'https://www.tiktok.com/@',
        'twitter' => 'https://x.com/',
        'x' => 'https://x.com/',
        'youtube' => 'https://www.youtube.com/@',
        'telegram' => 'https://t.me/',
    ];

    /**
     * Build a profile url from a bare handle ("marvadesigns", "@jane") once we
     * know the platform — people naturally type their name rather than hunt for
     * a url, and that shouldn't be a dead end.
     *
     * Returns null when it isn't handle-shaped or the platform is unknown; we
     * never guess a platform, since boosting the wrong account spends their
     * money on a stranger.
     */
    public static function fromHandle(string $input, string $platform): ?string
    {
        $handle = ltrim(trim($input), '@');
        $base = self::HANDLE_BASE[mb_strtolower(trim($platform))] ?? null;

        if ($base === null || $handle === '') {
            return null;
        }

        // Handles are a single token of letters/digits/._- — anything with a
        // space, slash or dot-com is a name or a broken url, not a handle.
        if (! preg_match('/^[A-Za-z0-9._-]{4,50}$/', $handle) || str_contains(mb_strtolower($handle), '.com')) {
            return null;
        }

        // Must contain a letter: "1000" is someone answering the quantity
        // question early, not a page name.
        if (! preg_match('/[A-Za-z]/', $handle)) {
            return null;
        }

        // Ordinary conversation typed at the link step is NOT a handle. Turning
        // "yes" into instagram.com/yes would buy followers for a stranger with
        // this customer's money, so anything that looks like an answer or a
        // command is refused and we ask again.
        $stop = [
            'yes', 'yeah', 'yebo', 'hongu', 'no', 'kwete', 'hatshi', 'ok', 'okay', 'sharp', 'bho',
            'cancel', 'stop', 'menu', 'help', 'back', 'done', 'paid', 'deposit', 'order', 'balance',
            'start', 'next', 'skip', 'wait', 'link', 'page', 'name', 'here', 'send', 'sure', 'thanks',
        ];
        if (in_array(mb_strtolower($handle), $stop, true)) {
            return null;
        }

        return $base.$handle;
    }

    /**
     * A customer-facing explanation when the link is the wrong shape, or null
     * when it's fine (or we can't tell — never block on a guess).
     */
    public static function mismatch(string $link, string $serviceName, string $serviceType = '', string $platform = ''): ?string
    {
        $wanted = self::wantedFor($serviceName, $serviceType);
        if ($wanted === null) {
            return null;
        }

        $actual = self::kindOf($link);
        if ($actual === $wanted) {
            return null;
        }

        // Always show what the right one looks like — telling someone their link
        // is wrong without showing the right shape just strands them.
        $example = self::exampleFor($platform, $serviceName, $serviceType);

        return $wanted === 'profile'
            ? "That link points to a *post*, but *{$serviceName}* needs your *page/profile* link — followers are added to the page itself, not to a post.\n\nExample: _{$example}_\n\nOn the app: open your page, tap *Share* → *Copy Link*, and send that here. 👍"
            : "That link points to a *page/profile*, but *{$serviceName}* needs the link to the specific *post or video* you want boosted.\n\nExample: _{$example}_\n\nOpen the post, tap *Share* → *Copy Link*, and send that here. 👍";
    }
}
