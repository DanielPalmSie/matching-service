<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken as BaseAbstractRefreshToken;

#[
    ORM\Entity(
        repositoryClass: RefreshTokenRepository::class,
    ),
    ORM\Table('refresh_tokens')
]
class RefreshToken extends BaseAbstractRefreshToken
{
    #[
        ORM\Id,
        ORM\Column(
            name: 'id',
            type: Types::INTEGER,
            nullable: false,
        ),
        ORM\GeneratedValue(strategy: 'AUTO'),
    ]
    protected $id;

    #[
        ORM\Column(
            name: 'refresh_token',
            type: Types::STRING,
            nullable: false,
        )
    ]
    protected $refreshToken;

    #[
        ORM\Column(
            name: 'username',
            type: Types::STRING,
            nullable: false,
        ),
    ]
    protected $username;

    #[
        ORM\Column(
            name: 'valid',
            type: Types::DATETIME_MUTABLE,
            nullable: false,
        )
    ]
    protected $valid;
}
