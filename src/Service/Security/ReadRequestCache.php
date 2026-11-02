<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\HttpFoundation\RequestStack;

/** Évite les lectures répétées pendant un affichage, sans conserver de droits en session. */
final readonly class ReadRequestCache
{
    public function __construct(private RequestStack $requests)
    {
    }

    /**
     * @template T
     *
     * @param \Closure(): T $load
     *
     * @return T
     */
    public function remember(string $key, \Closure $load): mixed
    {
        $request = $this->requests->getCurrentRequest();
        // Les écritures et commandes relisent toujours l'état courant.
        if (null === $request || !$request->isMethod('GET')) {
            return $load();
        }
        $attribute = '_backlinebridge.read.'.$key;
        if (!$request->attributes->has($attribute)) {
            $request->attributes->set($attribute, $load());
        }

        return $request->attributes->get($attribute);
    }
}
