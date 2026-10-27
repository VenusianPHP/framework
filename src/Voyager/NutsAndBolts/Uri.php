<?php

namespace Voyager\NutsAndBolts;

use Voyager\Contracts\NutsAndBolts\Htmlable;
use Voyager\NutsAndBolts\DataObjects\Stringable as SupportStringable;
use Voyager\NutsAndBolts\Concerns\Conditionable;
use Voyager\NutsAndBolts\Concerns\Dumpable;
use Voyager\NutsAndBolts\Concerns\Macroable;
use Voyager\NutsAndBolts\Concerns\Tappable;
use JsonSerializable;
use League\Uri\Contracts\UriInterface;
use League\Uri\Uri as LeagueUri;
use SensitiveParameter;
use Stringable;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\Str;
use Voyager\NutsAndBolts\DataObjects\Arr;

class Uri implements Htmlable, JsonSerializable, Stringable
{
    use Conditionable, Dumpable, Macroable, Tappable;

    /**
     * The URI instance.
     */
    protected UriInterface $uri;

    /**
     * Create a new parsed URI instance.
     */
    public function __construct(UriInterface|Stringable|string $uri = '')
    {
        $this->uri = $uri instanceof UriInterface ? $uri : LeagueUri::new((string) $uri);
    }

    /**
     * Create a new URI instance.
     */
    public static function of(UriInterface|Stringable|string $uri = ''): static
    {
        return new static($uri);
    }






    /**
     * Get the URI's authority.
     */
    public function authority(): ?string
    {
        return $this->uri->getAuthority();
    }

    /**
     * Get the URI's scheme.
     */
    public function scheme(): ?string
    {
        return $this->uri->getScheme();
    }

    /**
     * Get the user from the URI.
     */
    public function user(bool $withPassword = false): ?string
    {
        return $withPassword
            ? $this->uri->getUserInfo()
            : $this->uri->getUsername();
    }

    /**
     * Get the password from the URI.
     */
    public function password(): ?string
    {
        return $this->uri->getPassword();
    }

    /**
     * Get the URI's host.
     */
    public function host(): ?string
    {
        return $this->uri->getHost();
    }

    /**
     * Get the URI's port.
     */
    public function port(): ?int
    {
        return $this->uri->getPort();
    }

    /**
     * Get the URI's path.
     *
     * Empty or missing paths are returned as a single "/".
     *
     * @return non-empty-string
     */
    public function path(): string
    {
        $path = trim((string) $this->uri->getPath(), '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * Get the URI's path segments.
     *
     * Empty or missing paths are returned as an empty collection.
     */
    public function pathSegments(): Collection
    {
        $path = $this->path();

        return $path === '/' ? new Collection : new Collection(explode('/', $path));
    }

    /**
     * Get the URI's query string.
     */
    public function query(): UriQueryString
    {
        return new UriQueryString($this);
    }

    /**
     * Get the URI's fragment.
     */
    public function fragment(): ?string
    {
        return $this->uri->getFragment();
    }

    /**
     * Specify the scheme of the URI.
     */
    public function withScheme(Stringable|string $scheme): static
    {
        return new static($this->uri->withScheme($scheme));
    }

    /**
     * Specify the user and password for the URI.
     */
    public function withUser(Stringable|string|null $user, #[SensitiveParameter] Stringable|string|null $password = null): static
    {
        return new static($this->uri->withUserInfo($user, $password));
    }

    /**
     * Specify the host of the URI.
     */
    public function withHost(Stringable|string $host): static
    {
        return new static($this->uri->withHost($host));
    }

    /**
     * Specify the port of the URI.
     */
    public function withPort(?int $port): static
    {
        return new static($this->uri->withPort($port));
    }

    /**
     * Specify the path of the URI.
     */
    public function withPath(Stringable|string $path): static
    {
        return new static($this->uri->withPath(Str::start((string) $path, '/')));
    }

    /**
     * Merge new query parameters into the URI.
     */
    public function withQuery(array $query, bool $merge = true): static
    {
        foreach ($query as $key => $value) {
            $query[$key] = $this->queryValue($value);
        }

        if ($merge) {
            $mergedQuery = $this->query()->all();

            foreach ($query as $key => $value) {
                Arr::set($mergedQuery, $key, $value);
            }

            $newQuery = $mergedQuery;
        } else {
            $newQuery = [];

            foreach ($query as $key => $value) {
                Arr::set($newQuery, $key, $value);
            }
        }

        return new static($this->uri->withQuery(Arr::query($newQuery) ?: null));
    }

    /**
     * The value a query parameter is written with; an extension can turn its own objects into
     * plain values here.
     */
    protected function queryValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Merge new query parameters into the URI if they are not already in the query string.
     */
    public function withQueryIfMissing(array $query): static
    {
        $currentQuery = $this->query();

        foreach ($query as $key => $value) {
            if (! $currentQuery->missing($key)) {
                Arr::forget($query, $key);
            }
        }

        return $this->withQuery($query);
    }

    /**
     * Push a value onto the end of a query string parameter that is a list.
     */
    public function pushOntoQuery(string $key, mixed $value): static
    {
        $currentValue = data_get($this->query()->all(), $key);

        $values = Arr::wrap($value);

        return $this->withQuery([$key => match (true) {
            is_array($currentValue) && array_is_list($currentValue) => array_values(array_unique([...$currentValue, ...$values])),
            is_array($currentValue) => [...$currentValue, ...$values],
            ! is_null($currentValue) => [$currentValue, ...$values],
            default => $values,
        }]);
    }

    /**
     * Remove the given query parameters from the URI.
     */
    public function withoutQuery(array|string $keys): static
    {
        return $this->replaceQuery(Arr::except($this->query()->all(), $keys));
    }

    /**
     * Specify new query parameters for the URI.
     */
    public function replaceQuery(array $query): static
    {
        return $this->withQuery($query, merge: false);
    }

    /**
     * Specify the fragment of the URI.
     */
    public function withFragment(string $fragment): static
    {
        return new static($this->uri->withFragment($fragment));
    }

    /**
     * Remove the fragment from the URI.
     */
    public function withoutFragment(): static
    {
        return new static($this->uri->withFragment(null));
    }


    /**
     * Get the URI as a Stringable instance.
     *
     * @return \Voyager\NutsAndBolts\DataObjects\Stringable
     */
    public function toStringable()
    {
        return new SupportStringable($this->value());
    }


    /**
     * Get the URI as a string of HTML.
     *
     * @return string
     */
    public function toHtml(): string
    {
        return $this->value();
    }

    /**
     * Get the decoded string representation of the URI.
     */
    public function decode(): string
    {
        if (empty($this->query()->toArray())) {
            return $this->value();
        }

        return Str::replace($this->query()->value(), $this->query()->decode(), $this->value());
    }

    /**
     * Get the string representation of the URI.
     */
    public function value(): string
    {
        return $this->toString();
    }

    /**
     * Get the string representation of the URI.
     */
    public function toString(): string
    {
        return $this->uri->toString();
    }

    /**
     * Determine if the URI is currently an empty string.
     */
    public function isEmpty(): bool
    {
        return trim($this->value()) === '';
    }

    /**
     * Determine if the URI is not an empty string.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Dump the string representation of the URI.
     *
     * @param  mixed  ...$args
     * @return $this
     */
    public function dump(...$args)
    {
        dump($this->value(), ...$args);

        return $this;
    }


    /**
     * Get the underlying URI instance.
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Convert the object into a value that is JSON serializable.
     *
     * @return string
     */
    public function jsonSerialize(): string
    {
        return $this->value();
    }

    /**
     * Get the string representation of the URI.
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}
