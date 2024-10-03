<?php

namespace App\Commands;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class Export extends Command
{
    private ?string $token = null;

    private ?string $profileId = null;

    private array $readingStates = [];

    private array $bookResults = [];

    private array $combinedData = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export Literal books from your library';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $email = $this->ask('Account email address');
        $password = $this->secret('Account password');

        $this->info('Logging in...');

        if (! $this->login($email, $password)) {
            $this->fail('Could not log in.');
        }

        $this->info('Fetching reading states...');
        $this->fetchReadingStates();

        $this->info('Fetching book data...');
        $this->fetchBooks();

        $this->info('Compiling book information...');
        $this->withProgressBar($this->bookResults, function ($bookData) {
            $combinedBookData = [
                'title' => $bookData['title'] ?? null,
                'subtitle' => $bookData['subtitle'] ?? null,
                'isbn10' => $bookData['isbn10'] ?? null,
                'isbn13' => $bookData['isbn13'] ?? null,
                'publisher' => $bookData['publisher'] ?? null,
                'publishedDate' => $bookData['publishedDate'] ?? null,
                'authors' => array_map(static function ($item) {
                    return $item['name'] ?? null;
                }, $bookData['authors']),
                'pageCount' => $bookData['pageCount'] ?? null,
            ];

            foreach ($this->readingStates as $readingState) {
                if ($readingState['bookId'] === $bookData['id']) {
                    $combinedBookData['readingState'] = $readingState['status'];
                }
            }

            [$started, $finished] = $this->fetchReadDatesForBook($bookData['id']);

            $combinedBookData['started'] = $started ?? null;
            $combinedBookData['finished'] = $finished ?? null;

            $this->combinedData[] = $combinedBookData;
        });

        $this->newLine();
        $this->info('Writing JSON...');

        $filename = 'literal-export.json';
        File::put($filename, json_encode($this->combinedData));

        $this->comment('Done.');
    }

    private function fetchReadingStates(): void
    {
        $response = $this->queryAPI(
            File::get('storage/reading-states.gql'),
        );
        $data = $response->json('data');
        $this->readingStates = $data['myReadingStates'];
    }

    private function fetchReadDatesForBook($bookId): array
    {
        $response = $this->queryAPI(
            File::get('storage/read-dates.gql'),
            [
                'bookId' => $bookId,
                'profileId' => $this->profileId,
            ]
        );
        $data = $response->json('data')['getReadDates'][0] ?? null;

        if (! $data) {
            return [null, null];
        }

        return [
            $data['started'], $data['finished'],
        ];
    }

    private function fetchBooks($offset = 0): void
    {
        $gql = File::get('storage/books.gql');

        if ($offset > 0) {
            $gql = str_replace(
                'myBooks(limit: 200, offset: 0) {',
                'myBooks(limit: 200, offset: '.$offset.') {',
                $gql
            );
        }

        $response = $this->queryAPI($gql);
        $data = $response->json('data')['myBooks'];

        $this->bookResults = array_merge($this->bookResults, $data);

        if (count($data) === 200) {
            $this->fetchBooks($offset + 200);
        }
    }

    private function login($email, $password): bool
    {
        $response = $this->queryAPI(
            File::get('storage/login.gql'),
            [
                'email' => $email,
                'password' => $password,
            ]
        );
        $data = $response->json('data');

        $this->token = $data['login']['token'] ?? null;
        $this->profileId = $data['login']['profile']['id'] ?? null;

        return $this->token && $this->profileId;
    }

    private function queryAPI($gql, $arguments = []): PromiseInterface|Response
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer '.$this->token;
        }

        return Http::withHeaders($headers)
            ->post(config('app.apiBaseUrl'), [
                'query' => $gql,
                'variables' => $arguments,
            ]);
    }
}
