<?php

namespace App\Mcp\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Activity\ActorContext;
use App\Activity\SessionTracker;
use App\Entity\AgentSession;
use App\Mcp\Lookup;
use App\Mcp\Presenter;
use App\Mcp\ToolError;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Session\SessionInterface;
use Mcp\Server\Stateless\RequestMeta;
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
    protected SessionTracker $sessionTracker;
    protected string $client = 'mcp';
    /** Session MCP avec état (ère handshake), null en protocole sans état. */
    protected ?string $mcpSessionId = null;
    /** Identifiant de séance passé explicitement par l'agent. */
    protected ?string $explicitSessionId = null;
    private ?AgentSession $currentSession = null;

    /** Outil de lecture : pas de séance résolue ni créée. */
    protected const bool READ_ONLY = false;

    #[Required]
    public function setDependencies(
        EntityManagerInterface $em,
        Lookup $lookup,
        Presenter $presenter,
        ValidatorInterface $validator,
        ActorContext $actor,
        SessionTracker $sessionTracker,
    ): void {
        $this->em = $em;
        $this->lookup = $lookup;
        $this->presenter = $presenter;
        $this->validator = $validator;
        $this->actor = $actor;
        $this->sessionTracker = $sessionTracker;
    }

    /** @param T $data */
    abstract protected function handle(object $data): array;

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CallToolResult
    {
        $this->identify($context, $data);

        try {
            if (!static::READ_ONLY) {
                $this->currentSession();
            }
            $result = $this->handle($data);
        } catch (ToolError $e) {
            return new CallToolResult([new TextContent($e->getMessage())], true);
        }

        return new CallToolResult([new TextContent(json_encode($result, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES))]);
    }

    /**
     * Client et mode de protocole de l'appel.
     * Sans état, le nom du client arrive dans le _meta de chaque requête ; avec état, à l'initialisation.
     */
    private function identify(array $context, object $data): void
    {
        $session = $context['mcp_session'] ?? null;
        $meta = $session instanceof SessionInterface ? $session->get(RequestMeta::class) : null;

        if ($meta instanceof RequestMeta) {
            $this->client = $meta->clientInfo?->name ?? 'mcp';
            $this->mcpSessionId = null;
        } elseif ($session instanceof SessionInterface) {
            $this->client = $session->get('client_info')['name'] ?? 'mcp';
            $this->mcpSessionId = $session->getId()->toRfc4122();
        } else {
            $this->client = 'mcp';
            $this->mcpSessionId = null;
        }

        $explicit = property_exists($data, 'session') ? trim((string) $data->session) : '';
        $this->explicitSessionId = '' === $explicit ? null : $explicit;
        $this->currentSession = null;
        $this->actor->set($this->client);
    }

    /** Séance de l'appel, résolue une fois puis utilisée pour signer le journal. */
    protected function currentSession(): AgentSession
    {
        if (null === $this->currentSession) {
            $this->currentSession = $this->resolveSession();
            $this->actor->set($this->client, $this->currentSession->getSessionId());
        }

        return $this->currentSession;
    }

    protected function resolveSession(): AgentSession
    {
        if (null !== $this->explicitSessionId) {
            return $this->sessionTracker->find($this->explicitSessionId)
                ?? throw new ToolError(\sprintf('Séance "%s" introuvable. Appelle start_session pour en ouvrir une.', $this->explicitSessionId));
        }

        return null !== $this->mcpSessionId
            ? $this->sessionTracker->track($this->mcpSessionId, $this->client)
            : $this->sessionTracker->current($this->client);
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
