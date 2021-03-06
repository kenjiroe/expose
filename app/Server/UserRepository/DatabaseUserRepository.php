<?php

namespace App\Server\UserRepository;

use App\Contracts\UserRepository;
use Clue\React\SQLite\DatabaseInterface;
use Clue\React\SQLite\Result;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class DatabaseUserRepository implements UserRepository
{
    /** @var DatabaseInterface */
    protected $database;

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    public function getUsers(): PromiseInterface
    {
        $deferred = new Deferred();

        $this->database
            ->query('SELECT * FROM users ORDER by created_at DESC')
            ->then(function (Result $result) use ($deferred) {
                $deferred->resolve($result->rows);
            });

        return $deferred->promise();
    }

    public function paginateUsers(int $perPage, int $currentPage): PromiseInterface
    {
        $deferred = new Deferred();

        $this->database
            ->query('SELECT * FROM users ORDER by created_at DESC LIMIT :limit OFFSET :offset', [
                'limit' => $perPage + 1,
                'offset' => $currentPage < 2 ? 0 : ($currentPage - 1) * $perPage,
            ])
            ->then(function (Result $result) use ($deferred, $perPage, $currentPage) {
                if (count($result->rows) == $perPage + 1) {
                    array_pop($result->rows);
                    $nextPage = $currentPage + 1;
                }

                $paginated = [
                    'users' => $result->rows,
                    'current_page' => $currentPage,
                    'per_page' => $perPage,
                    'next_page' => $nextPage ?? null,
                    'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
                ];

                $deferred->resolve($paginated);
            });

        return $deferred->promise();
    }

    public function getUserById($id): PromiseInterface
    {
        $deferred = new Deferred();

        $this->database
            ->query('SELECT * FROM users WHERE id = :id', ['id' => $id])
            ->then(function (Result $result) use ($deferred) {
                $deferred->resolve($result->rows[0] ?? null);
            });

        return $deferred->promise();
    }

    public function getUserByToken(string $authToken): PromiseInterface
    {
        $deferred = new Deferred();

        $this->database
            ->query('SELECT * FROM users WHERE auth_token = :token', ['token' => $authToken])
            ->then(function (Result $result) use ($deferred) {
                $deferred->resolve($result->rows[0] ?? null);
            });

        return $deferred->promise();
    }

    public function storeUser(array $data): PromiseInterface
    {
        $deferred = new Deferred();

        $this->database->query("
            INSERT INTO users (name, auth_token, created_at)
            VALUES (:name, :auth_token, DATETIME('now'))
        ", $data)
            ->then(function (Result $result) use ($deferred) {
                $this->database->query('SELECT * FROM users WHERE id = :id', ['id' => $result->insertId])
                    ->then(function (Result $result) use ($deferred) {
                        $deferred->resolve($result->rows[0]);
                    });
            });

        return $deferred->promise();
    }

    public function deleteUser($id): PromiseInterface
    {
        $deferred = new Deferred();

        $this->database->query('DELETE FROM users WHERE id = :id', ['id' => $id])
            ->then(function (Result $result) use ($deferred) {
                $deferred->resolve($result);
            });

        return $deferred->promise();
    }
}
