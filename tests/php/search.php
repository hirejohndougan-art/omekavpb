<?php
// Pure PHP checks for VPB Search's text pieces (module/VisualPageBuilder/src/Search).
// Run: php tests/php/search.php   (exit code 1 on any failure)
spl_autoload_register(function ($class) {
    $prefix = 'VisualPageBuilder\\';
    if (strpos($class, $prefix) === 0) {
        $file = __DIR__ . '/../../module/VisualPageBuilder/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use VisualPageBuilder\Search\DateParser;
use VisualPageBuilder\Search\QueryParser;
use VisualPageBuilder\Search\Stemmer;
use VisualPageBuilder\Search\Synonyms;
use VisualPageBuilder\Search\Typo;

$pass = 0;
$fail = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
    } else {
        $fail[] = $name . ($detail !== '' ? " ($detail)" : '');
    }
}

// --- Stemmer -------------------------------------------------------------
check('depots -> depot', Stemmer::stem('depots') === 'depot');
check('railroads -> railroad', Stemmer::stem('railroads') === 'railroad');
check('depot and depots share a stem', Stemmer::stem('depot') === Stemmer::stem('depots'));
check('stories -> story', Stemmer::stem('stories') === 'story');
check('glasses keeps ss', Stemmer::stem('glasses') === 'glass');
check('bus is untouched', Stemmer::stem('bus') === 'bus');
check('photographing -> photograph', Stemmer::stem('photographing') === 'photograph');
check('chatted -> chat', Stemmer::stem('chatted') === 'chat');
check('short word untouched', Stemmer::stem('rr') === 'rr');

// --- Date parser ---------------------------------------------------------
check('bare year', DateParser::parse('1910') === ['start' => 1910, 'end' => 1910]);
check('about 1910 uses window 5', DateParser::parse('about 1910') === ['start' => 1905, 'end' => 1915]);
check('ca. 1910 is approximate', DateParser::parse('ca. 1910') === ['start' => 1905, 'end' => 1915]);
check('circa 1910 is approximate', DateParser::parse('circa 1910') === ['start' => 1905, 'end' => 1915]);
check('1920s is the decade', DateParser::parse('1920s') === ['start' => 1920, 'end' => 1929]);
check('early 1930s', DateParser::parse('early 1930s') === ['start' => 1930, 'end' => 1934]);
check('late 1930s', DateParser::parse('late 1930s') === ['start' => 1936, 'end' => 1939]);
check('en dash range', DateParser::parse('1912–1918') === ['start' => 1912, 'end' => 1918]);
check('hyphen range', DateParser::parse('1912-1918') === ['start' => 1912, 'end' => 1918]);
check('full date gives its year', DateParser::parse('March 3, 1912') === ['start' => 1912, 'end' => 1912]);
check('brackets are stripped', DateParser::parse('[1910?]') === ['start' => 1910, 'end' => 1910]);
check('[year] means undated', DateParser::parse('[year]') === null);
check('empty means undated', DateParser::parse('') === null);
check('no year means undated', DateParser::parse('unknown') === null);

// --- Typo ----------------------------------------------------------------
$vocab = ['Dickinson' => 40, 'Dickens' => 3, 'depot' => 20, 'rail' => 9];
check('Dickenson -> Dickinson', Typo::suggest('Dickenson', $vocab) === 'Dickinson');
check('exact word has no suggestion', Typo::suggest('depot', $vocab) === null);
check('short word must match exactly', Typo::suggest('rale', $vocab) === null);
check('no close word', Typo::suggest('zzzzzzzzz', $vocab) === null);
check('suggestion keeps its casing', Typo::suggest('dickenson', $vocab) === 'Dickinson');

// --- Synonyms ------------------------------------------------------------
$syn = Synonyms::fromText("rr = railroad, rail road\nstation = depot\n\nbad line");
check('rr expands to railroad', in_array('railroad', $syn->expand('rr'), true));
check('expansion keeps the word', in_array('rr', $syn->expand('rr'), true));
check('unknown word expands to itself', $syn->expand('barn') === ['barn']);
check('synonyms are one way', $syn->expand('depot') === ['depot']);

// --- Query parser --------------------------------------------------------
$q = QueryParser::parse('depots 1910s', $syn);
check('year is pulled out', $q['years'] === ['start' => 1910, 'end' => 1919]);
check('year is not a term', $q['terms'] === ['depot']);
$q = QueryParser::parse('"oral history" interview');
check('phrase is kept', $q['phrases'] === ['oral history']);
check('words around a phrase are terms', $q['terms'] === ['interview']);
$q = QueryParser::parse('rr', $syn);
check('synonym adds its terms, multi-word ones split', $q['terms'] === ['rr', 'railroad', 'rail', 'road']);
$q = QueryParser::parse('rail');
check('last word is a prefix while typing', $q['prefixLast'] === true);
$q = QueryParser::parse('rail ');
check('a trailing space ends the prefix', $q['prefixLast'] === false);
$q = QueryParser::parse('the and of');
check('only stopwords gives no terms', $q['terms'] === [] && $q['prefixLast'] === false);
$q = QueryParser::parse('about 1910 Galveston');
check('about-year is pulled out', $q['years'] === ['start' => 1905, 'end' => 1915] && $q['terms'] === ['galveston']);

echo "Search checks: $pass passed";
if ($fail) {
    echo ", " . count($fail) . " failed:\n - " . implode("\n - ", $fail) . "\n";
    exit(1);
}
echo "\n";
