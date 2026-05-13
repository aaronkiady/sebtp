<?php

namespace App\Entity;

use App\Repository\SebtpRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SebtpRepository::class)]
class Sebtp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $instance = null;

    #[ORM\Column(length: 255)]
    private ?string $nomOrganisme = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mandat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomRepresentant = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fichiers = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstance(): ?string
    {
        return $this->instance;
    }

    public function setInstance(string $instance): static
    {
        $this->instance = $instance;

        return $this;
    }

    public function getNomOrganisme(): ?string
    {
        return $this->nomOrganisme;
    }

    public function setNomOrganisme(string $nomOrganisme): static
    {
        $this->nomOrganisme = $nomOrganisme;

        return $this;
    }

    public function getMandat(): ?string
    {
        return $this->mandat;
    }

    public function setMandat(?string $mandat): static
    {
        $this->mandat = $mandat;

        return $this;
    }

    public function getNomRepresentant(): ?string
    {
        return $this->nomRepresentant;
    }

    public function setNomRepresentant(?string $nomRepresentant): static
    {
        $this->nomRepresentant = $nomRepresentant;

        return $this;
    }

    public function getObservation(): ?string
    {
        return $this->observation;
    }

    public function setObservation(?string $observation): static
    {
        $this->observation = $observation;

        return $this;
    }

    public function getFichiers(): ?string
    {
        return $this->fichiers;
    }

    public function setFichiers(?string $fichiers): static
    {
        $this->fichiers = $fichiers;

        return $this;
    }
}
