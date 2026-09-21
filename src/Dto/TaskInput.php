<?php

namespace App\Dto;

use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Request body of POST /api/tasks.
 *
 * The Symfony serializer fills it from JSON, the validator checks the fields,
 * and the controller then maps it onto the Task entity.
 */
class TaskInput
{
    #[Assert\NotBlank(message: 'The "title" field is required.')]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    public ?string $status = null;

    public ?string $priority = null;

    /** Date in YYYY-MM-DD format. */
    public ?string $dueDate = null;

    public ?int $categoryId = null;

    #[Assert\Callback]
    public function validateStatus(ExecutionContextInterface $context): void
    {
        if (null !== $this->status && null === TaskStatus::tryFrom($this->status)) {
            $context->buildViolation('Unknown status. Allowed values: {{ values }}.')
                ->setParameter('{{ values }}', implode(', ', array_column(TaskStatus::cases(), 'value')))
                ->atPath('status')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validatePriority(ExecutionContextInterface $context): void
    {
        if (null !== $this->priority && null === TaskPriority::tryFrom($this->priority)) {
            $context->buildViolation('Unknown priority. Allowed values: {{ values }}.')
                ->setParameter('{{ values }}', implode(', ', array_column(TaskPriority::cases(), 'value')))
                ->atPath('priority')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateDueDate(ExecutionContextInterface $context): void
    {
        if (null === $this->dueDate) {
            return;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $this->dueDate);
        if (false === $date || $date->format('Y-m-d') !== $this->dueDate) {
            $context->buildViolation('The due date must be a valid date in YYYY-MM-DD format.')
                ->atPath('dueDate')
                ->addViolation();
        }
    }
}
