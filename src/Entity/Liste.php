<?php

namespace App\Entity;

use App\Repository\ListeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ListeRepository::class)]
class Liste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numero = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siteWeb = null;

    #[ORM\Column(length: 255)]
    private ?string $activite = null;

    #[ORM\Column(length: 255)]
    private ?string $filiere = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nbEmployes = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cotFMTP = null;

    #[ORM\Column(length: 255)]
    private ?string $dg = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresseDg = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephoneDg = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statutMenmbre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fonctionSEBTP = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mandat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observation = null;

    /**
     * @var Collection<int, Formation>
     */
    #[ORM\ManyToMany(targetEntity: Formation::class, mappedBy: 'participants')]
    private Collection $formations;

    /**
     * @var Collection<int, Cotisation>
     */
    #[ORM\OneToMany(mappedBy: 'adherent', targetEntity: Cotisation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $cotisations;

    #[ORM\OneToMany(mappedBy: 'adherent', targetEntity: Participation::class, orphanRemoval: true)]
    private Collection $participations;

    /**
     * @var Collection<int, Contact>
     */
    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'liste')]
    private Collection $contacts;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $raisonDepart = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statutDemande = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $validationBureau = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $validationAG = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    public function __construct()
    {
        $this->formations = new ArrayCollection();
        $this->cotisations = new ArrayCollection();
        $this->contacts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getSiteWeb(): ?string
    {
        return $this->siteWeb;
    }

    public function setSiteWeb(?string $siteWeb): static
    {
        $this->siteWeb = $siteWeb;
        return $this;
    }

    public function getActivite(): ?string
    {
        return $this->activite;
    }

    public function setActivite(string $activite): static
    {
        $this->activite = $activite;
        return $this;
    }

    public function getFiliere(): ?string
    {
        return $this->filiere;
    }

    public function setFiliere(string $filiere): static
    {
        $this->filiere = $filiere;
        return $this;
    }

    public function getNbEmployes(): ?string
    {
        return $this->nbEmployes;
    }

    public function setNbEmployes(?string $nbEmployes): static
    {
        $this->nbEmployes = $nbEmployes;
        return $this;
    }

    public function getCotFMTP(): ?string
    {
        return $this->cotFMTP;
    }

    public function setCotFMTP(?string $cotFMTP): static
    {
        $this->cotFMTP = $cotFMTP;
        return $this;
    }

    public function getDg(): ?string
    {
        return $this->dg;
    }

    public function setDg(string $dg): static
    {
        $this->dg = $dg;
        return $this;
    }

    public function getAdresseDg(): ?string
    {
        return $this->adresseDg;
    }

    public function setAdresseDg(?string $adresseDg): static
    {
        $this->adresseDg = $adresseDg;
        return $this;
    }

    public function getTelephoneDg(): ?string
    {
        return $this->telephoneDg;
    }

    public function setTelephoneDg(?string $telephoneDg): static
    {
        $this->telephoneDg = $telephoneDg;
        return $this;
    }

    public function getStatutMenmbre(): ?string
    {
        return $this->statutMenmbre;
    }

    public function setStatutMenmbre(?string $statutMenmbre): static
    {
        $this->statutMenmbre = $statutMenmbre;
        return $this;
    }

    public function getFonctionSEBTP(): ?string
    {
        return $this->fonctionSEBTP;
    }

    public function setFonctionSEBTP(?string $fonctionSEBTP): static
    {
        $this->fonctionSEBTP = $fonctionSEBTP;
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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
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

    /**
     * @return Collection<int, Formation>
     */
    public function getFormations(): Collection
    {
        return $this->formations;
    }

    public function addFormation(Formation $formation): static
    {
        if (!$this->formations->contains($formation)) {
            $this->formations->add($formation);
            $formation->addParticipant($this);
        }
        return $this;
    }

    public function removeFormation(Formation $formation): static
    {
        if ($this->formations->removeElement($formation)) {
            $formation->removeParticipant($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, Cotisation>
     */
    public function getCotisations(): Collection
    {
        return $this->cotisations;
    }

    public function addCotisation(Cotisation $cotisation): static
    {
        if (!$this->cotisations->contains($cotisation)) {
            $this->cotisations->add($cotisation);
            $cotisation->setAdherent($this);
        }
        return $this;
    }

    public function removeCotisation(Cotisation $cotisation): static
    {
        if ($this->cotisations->removeElement($cotisation)) {
            if ($cotisation->getAdherent() === $this) {
                $cotisation->setAdherent(null);
            }
        }
        return $this;
    }

   /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setAdherent($this);
        }

        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getAdherent() === $this) {
                $participation->setAdherent(null);
            }
        }

        return $this;
    }

    public function isAjour(): bool
    {
        $currentYear = date('Y');
        foreach ($this->cotisations as $cotisation) {
            if ($cotisation->getPeriode() === $currentYear && $cotisation->getStatut() === 'payé') {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setListe($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            if ($contact->getListe() === $this) {
                $contact->setListe(null);
            }
        }

        return $this;
    }

    public function getRaisonDepart(): ?string
    {
        return $this->raisonDepart;
    }

    public function setRaisonDepart(?string $raisonDepart): static
    {
        $this->raisonDepart = $raisonDepart;

        return $this;
    }

    public function getStatutDemande(): ?string
    {
        return $this->statutDemande;
    }

    public function setStatutDemande(?string $statutDemande): static
    {
        $this->statutDemande = $statutDemande;

        return $this;
    }

    public function getValidationBureau(): ?string
    {
        return $this->validationBureau;
    }

    public function setValidationBureau(?string $validationBureau): static
    {
        $this->validationBureau = $validationBureau;

        return $this;
    }

    public function getValidationAG(): ?string
    {
        return $this->validationAG;
    }

    public function setValidationAG(?string $validationAG): static
    {
        $this->validationAG = $validationAG;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }
}