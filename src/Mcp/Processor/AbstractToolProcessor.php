<?php

namespace App\Mcp\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Activity\ActorContext;
use App\Mcp\Lookup;
use App\Mcp\Presenter;
use App\Mcp\ToolError;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Session\SessionInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Base des outils MCP : identifie l'agent, convertit les erreurs métier
 * en résultat d'erreur lisible et encode la réponse en JSON.
 *
 * @template T of object
 *
 * @implements ProcessorInterface<T, CallToolResult>
 */
abstract class AbstractToolProcessor implements ProcessorInterface
{
    protected EntityManagerInterface $em;
    protected Lookup $lookup;
    protected Presenter $presenter;
    protected ValidatorInterface $validator;
    private ActorContext $actor;

    #[Required]
    public function setDependencies(
        EntityManagerInterface $em,
        Lookup $lookup,
        Presenter $presenter,
        ValidatorInterface $validator,
        ActorContext $actor,
    ): void {
        $this->em = $em;
        $this->lookup = $lookup;
        $this->presenter = $presenter;
        $this->validator = $validator;
        $this->actor = $actor;
    }

    /** @param T $data */
    abstract protected function handle(object $data): array;

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CallToolResult
    {
        $session = $context['mcp_session'] ?? null;
        if ($session instanceof SessionInterface) {
            $client = $session->get('client_info');
            $this->actor->set($client['name'] ?? 'mcp', $session->getId()->toRfc4122());
        } else {
            $this->actor->set('mcp');
        }

        try {
            $result = $this->handle($data);
        } catch (ToolError $e) {
            return new CallToolResult([new TextContent($e->getMessage())], true);
        }

        return new CallToolResult([new TextContent(json_encode($result, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES))]);
    }

    /** Valide les entités modifiées, puis enregistre. */
    protected function save(object ...$entities): void
    {
        $messages = [];
        foreach ($entities as $entity) {
            foreach ($this->validator->validate($entity) as $violation) {
                $path = $violation->getPropertyPath();
                $messages[] = ('' !== $path ? $path.' : ' : '').$violation->getMessage();
            }
        }
        if ([] !== $messages) {
            throw new ToolError("Données invalides :\n- ".implode("\n- ", array_unique($messages)));
        }

        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    /**
     * Convertit une valeur texte en enum, avec la liste des valeurs admises en cas d'erreur.
     *
     * @template E of \BackedEnum
     *
     * @param class-string<E> $enum
     *
     * @return E|null
     */
    protected function enum(string $enum, ?string $value, string $field): ?\BackedEnum
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return $enum::tryFrom(strtolower(trim($value)))
            ?? throw new ToolError(\sprintf('%s : "%s" invalide. Valeurs admises : %s.', $field, $value, implode(', ', array_column($enum::cases(), 'value'))));
    }

    protected function date(?string $value, string $field): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value)
            ?: throw new ToolError(\sprintf('%s : "%s" invalide, format attendu AAAA-MM-JJ.', $field, $value));
    }
}
