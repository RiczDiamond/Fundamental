<?php

class ContentTypeRegistry
{
    public function getAll()
    {
        return [
            'services' => [
                'label' => 'Diensten',
                'slug' => 'services',
                'description' => 'Servicepagina\'s met details en CTA.',
                'fields' => [
                    ['name' => 'cta_label', 'label' => 'CTA label', 'type' => 'text'],
                    ['name' => 'cta_url', 'label' => 'CTA URL', 'type' => 'text'],
                    ['name' => 'price_hint', 'label' => 'Prijs indicatie', 'type' => 'text'],
                ],
            ],
            'portfolio' => [
                'label' => 'Cases / Portfolio',
                'slug' => 'portfolio',
                'description' => 'Projecten met resultaat en klantquote.',
                'fields' => [
                    ['name' => 'client_name', 'label' => 'Klant', 'type' => 'text'],
                    ['name' => 'result_metric', 'label' => 'Resultaat', 'type' => 'text'],
                    ['name' => 'quote', 'label' => 'Klantquote', 'type' => 'textarea'],
                ],
            ],
            'team' => [
                'label' => 'Teamleden',
                'slug' => 'team',
                'description' => 'Medewerkers met rol en expertise.',
                'fields' => [
                    ['name' => 'role', 'label' => 'Rol', 'type' => 'text'],
                    ['name' => 'expertise', 'label' => 'Expertise', 'type' => 'textarea'],
                    ['name' => 'linkedin_url', 'label' => 'LinkedIn URL', 'type' => 'text'],
                ],
            ],
            'jobs' => [
                'label' => 'Vacatures',
                'slug' => 'vacatures',
                'description' => 'Functieprofielen met openingsdatum en status.',
                'fields' => [
                    ['name' => 'location', 'label' => 'Locatie', 'type' => 'text'],
                    ['name' => 'employment_type', 'label' => 'Dienstverband', 'type' => 'text'],
                    ['name' => 'apply_url', 'label' => 'Solliciteer URL', 'type' => 'text'],
                ],
            ],
            'testimonials' => [
                'label' => 'Testimonials / Reviews',
                'slug' => 'testimonials',
                'description' => 'Klantervaringen en aanbevelingen.',
                'fields' => [
                    ['name' => 'client_name', 'label' => 'Klantnaam', 'type' => 'text'],
                    ['name' => 'client_role', 'label' => 'Functie klant', 'type' => 'text'],
                    ['name' => 'rating', 'label' => 'Score (1-5)', 'type' => 'text'],
                ],
            ],
            'faq' => [
                'label' => 'FAQ',
                'slug' => 'faq',
                'description' => 'Veelgestelde vragen per onderwerp.',
                'fields' => [
                    ['name' => 'question', 'label' => 'Vraag', 'type' => 'text'],
                    ['name' => 'topic', 'label' => 'Onderwerp', 'type' => 'text'],
                    ['name' => 'answer', 'label' => 'Kort antwoord', 'type' => 'textarea'],
                ],
            ],
            'events' => [
                'label' => 'Evenementen / Webinars',
                'slug' => 'events',
                'description' => 'Events met datum, locatie en inschrijving.',
                'fields' => [
                    ['name' => 'location', 'label' => 'Locatie', 'type' => 'text'],
                    ['name' => 'register_url', 'label' => 'Inschrijf URL', 'type' => 'text'],
                    ['name' => 'event_kind', 'label' => 'Type event', 'type' => 'text'],
                ],
            ],
            'downloads' => [
                'label' => 'Downloads / Resources',
                'slug' => 'downloads',
                'description' => 'Whitepapers, brochures en checklists.',
                'fields' => [
                    ['name' => 'file_url', 'label' => 'Bestands URL', 'type' => 'text'],
                    ['name' => 'file_type', 'label' => 'Bestandstype', 'type' => 'text'],
                    ['name' => 'file_size', 'label' => 'Bestandsgrootte', 'type' => 'text'],
                ],
            ],
            'knowledge_base' => [
                'label' => 'Kennisbank / Help',
                'slug' => 'kennisbank',
                'description' => 'Support- en helpartikelen.',
                'fields' => [
                    ['name' => 'topic', 'label' => 'Onderwerp', 'type' => 'text'],
                    ['name' => 'difficulty', 'label' => 'Niveau', 'type' => 'text'],
                    ['name' => 'support_url', 'label' => 'Support URL', 'type' => 'text'],
                ],
            ],
            'partners' => [
                'label' => 'Partners / Klanten logo\'s',
                'slug' => 'partners',
                'description' => 'Social proof met partner- en klantlogo\'s.',
                'fields' => [
                    ['name' => 'website_url', 'label' => 'Website', 'type' => 'text'],
                    ['name' => 'logo_url', 'label' => 'Logo URL', 'type' => 'text'],
                    ['name' => 'partnership_type', 'label' => 'Samenwerking', 'type' => 'text'],
                ],
            ],
        ];
    }

    public function getKeys()
    {
        return array_keys($this->getAll());
    }

    public function getByKey($key)
    {
        $key = trim((string)$key);
        $all = $this->getAll();
        return $all[$key] ?? null;
    }

    public function getBySlug($slug)
    {
        $slug = strtolower(trim((string)$slug));
        foreach ($this->getAll() as $key => $definition) {
            if (strtolower((string)($definition['slug'] ?? '')) === $slug) {
                return ['key' => $key, 'definition' => $definition];
            }
        }
        return null;
    }

    public function isValidKey($key)
    {
        return $this->getByKey($key) !== null;
    }

    public function isValidSlug($slug)
    {
        return $this->getBySlug($slug) !== null;
    }
}
