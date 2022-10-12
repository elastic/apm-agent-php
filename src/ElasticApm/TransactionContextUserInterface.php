<?php

declare(strict_types=1);

namespace Elastic\Apm;

interface TransactionContextUserInterface
{
    /**
     * Identifier of the logged in user
     *
     * The length of a string value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/user.json#L6
     *
     * @param null|int|string $id
     *
     * @return void
     */
    public function setId($id): void;

    /**
     * Email of the logged in user
     *
     * The length of a string value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/user.json#L11
     *
     * @param ?string $email
     *
     * @return void
     */
    public function setEmail(?string $email): void;

    /**
     * The username of the logged in user
     *
     * The length of a string value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/user.json#L16
     *
     * @param ?string $username
     *
     * @return void
     */
    public function setUsername(?string $username): void;
}
