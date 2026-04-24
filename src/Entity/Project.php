<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'projects')]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: "project_name", length: 255)]
    private ?string $projectName = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $materials = null;

    #[ORM\Column(name: "workers_count", type: "integer", nullable: true)]
    private ?int $workersCount = null;

    #[ORM\Column(type: "decimal", precision: 15, scale: 2, nullable: true)]
    private ?string $budget = null;

    #[ORM\Column(name: "deadline_start", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $deadlineStart = null;

    #[ORM\Column(name: "deadline_finish", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $deadlineFinish = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $place = null;

    #[ORM\Column(name: "create_at", type: "datetime")]
    private ?\DateTimeInterface $createAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(name: "owner_id", referencedColumnName: "id", nullable: false)]
    private ?User $owner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjectName(): ?string
    {
        return $this->projectName;
    }

    public function setProjectName(string $projectName): self
    {
        $this->projectName = $projectName;
        return $this;
    }

    public function getMaterials(): ?string
    {
        return $this->materials;
    }

    public function setMaterials(?string $materials): self
    {
        $this->materials = $materials;
        return $this;
    }

    public function getWorkersCount(): ?int
    {
        return $this->workersCount;
    }

    public function setWorkersCount(?int $workersCount): self
    {
        $this->workersCount = $workersCount;
        return $this;
    }

    public function getBudget(): ?string
    {
        return $this->budget;
    }

    public function setBudget(?string $budget): self
    {
        $this->budget = $budget;
        return $this;
    }

    public function getDeadlineStart(): ?\DateTimeInterface
    {
        return $this->deadlineStart;
    }

    public function setDeadlineStart(?\DateTimeInterface $deadlineStart): self
    {
        $this->deadlineStart = $deadlineStart;
        return $this;
    }

    public function getDeadlineFinish(): ?\DateTimeInterface
    {
        return $this->deadlineFinish;
    }

    public function setDeadlineFinish(?\DateTimeInterface $deadlineFinish): self
    {
        $this->deadlineFinish = $deadlineFinish;
        return $this;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function setPlace(?string $place): self
    {
        $this->place = $place;
        return $this;
    }

    public function getCreateAt(): ?\DateTimeInterface
    {
        return $this->createAt;
    }

    public function setCreateAt(\DateTimeInterface $createAt): self
    {
        $this->createAt = $createAt;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }
}
