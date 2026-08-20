<?php

namespace App\Entity;

use App\Repository\PartenariatsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartenariatsRepository::class)]
class Partenariats
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Partenaire = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Contenu = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $DateDebut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $DateFin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $Observation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Fichier = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartenaire(): ?string
    {
        return $this->Partenaire;
    }

    public function setPartenaire(?string $Partenaire): static
    {
        $this->Partenaire = $Partenaire;

        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->Contenu;
    }

    public function setContenu(?string $Contenu): static
    {
        $this->Contenu = $Contenu;

        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->DateDebut;
    }

    public function setDateDebut(?\DateTime $DateDebut): static
    {
        $this->DateDebut = $DateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->DateFin;
    }

    public function setDateFin(?\DateTime $DateFin): static
    {
        $this->DateFin = $DateFin;

        return $this;
    }

    public function getObservation(): ?string
    {
        return $this->Observation;
    }

    public function setObservation(?string $Observation): static
    {
        $this->Observation = $Observation;

        return $this;
    }

    public function getFichier(): ?string
    {
        return $this->Fichier;
    }

    public function setFichier(?string $Fichier): static
    {
        $this->Fichier = $Fichier;

        return $this;
    }
}
