import os
import sys
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, HRFlowable, KeepTogether

def build_pdf(filename="note_conceptuelle.pdf"):
    doc = SimpleDocTemplate(
        filename,
        pagesize=A4,
        leftMargin=32,
        rightMargin=32,
        topMargin=26,
        bottomMargin=26
    )

    story = []
    
    # Palette SafarCar: Forest Dark Green (#052b24), Emerald Brand (#0d9488), Slate Text (#1e293b), Border Slate (#e2e8f0)
    c_primary = colors.HexColor("#06231e")
    c_brand = colors.HexColor("#0d9488")
    c_accent = colors.HexColor("#10b981")
    c_text = colors.HexColor("#1e293b")
    c_muted = colors.HexColor("#475569")
    c_card_bg = colors.HexColor("#f8fafc")
    c_border = colors.HexColor("#cbd5e1")

    styles = getSampleStyleSheet()
    
    # Custom tight styles to fit exactly on 1 page A4
    style_header_title = ParagraphStyle(
        'HeaderTitle',
        fontName='Helvetica-Bold',
        fontSize=13,
        leading=15,
        textColor=c_primary,
        alignment=0
    )
    style_header_sub = ParagraphStyle(
        'HeaderSub',
        fontName='Helvetica',
        fontSize=7.5,
        leading=9.5,
        textColor=c_brand,
        alignment=0
    )
    style_candidate = ParagraphStyle(
        'Candidate',
        fontName='Helvetica-Bold',
        fontSize=7.5,
        leading=9.5,
        textColor=c_text,
        alignment=2
    )
    style_candidate_sub = ParagraphStyle(
        'CandidateSub',
        fontName='Helvetica',
        fontSize=7,
        leading=9,
        textColor=c_muted,
        alignment=2
    )

    style_sec_title = ParagraphStyle(
        'SecTitle',
        fontName='Helvetica-Bold',
        fontSize=8.5,
        leading=10.5,
        textColor=c_primary
    )
    style_body = ParagraphStyle(
        'Body',
        fontName='Helvetica',
        fontSize=6.9,
        leading=8.6,
        textColor=c_text
    )
    style_bullet = ParagraphStyle(
        'Bullet',
        fontName='Helvetica',
        fontSize=6.8,
        leading=8.4,
        textColor=c_text
    )
    style_tag = ParagraphStyle(
        'Tag',
        fontName='Helvetica-Bold',
        fontSize=6.5,
        leading=7.5,
        textColor=colors.white
    )

    # 1. Header Table
    header_data = [
        [
            Paragraph("<b>NOTE CONCEPTUELLE D'INGÉNIERIE — SAFARCAR MAROC</b>", style_header_title),
            Paragraph("<b>Candidat : Moaad Daoudi</b>", style_candidate)
        ],
        [
            Paragraph("PFE Challenge EMC 2026 · Technopark Souss-Massa · Sujet 3 : Covoiturage Régional Multi-Tronçons", style_header_sub),
            Paragraph("Ingénieur d'État en Transformation Digitale & IA · ENSA Al Hoceima", style_candidate_sub)
        ]
    ]
    header_table = Table(header_data, colWidths=[355, 175])
    header_table.setStyle(TableStyle([
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('BOTTOMPADDING', (0,0), (-1,-1), 1),
        ('TOPPADDING', (0,0), (-1,-1), 0),
        ('LEFTPADDING', (0,0), (-1,-1), 0),
        ('RIGHTPADDING', (0,0), (-1,-1), 0),
    ]))
    story.append(header_table)
    story.append(Spacer(1, 4))
    story.append(HRFlowable(width="100%", thickness=1.5, color=c_brand, spaceBefore=1, spaceAfter=5))

    # Helper function for card boxes
    def make_section(title, content_list, bg_color=c_card_bg):
        items = [
            Paragraph(f"<b><font color='{c_brand.hexval()}'>■</font> {title.upper()}</b>", style_sec_title),
            Spacer(1, 2)
        ]
        for it in content_list:
            if isinstance(it, str):
                items.append(Paragraph(it, style_body))
                items.append(Spacer(1, 1.5))
            else:
                items.append(it)
        
        t = Table([[items]], colWidths=[530])
        t.setStyle(TableStyle([
            ('BACKGROUND', (0,0), (-1,-1), bg_color),
            ('BOX', (0,0), (-1,-1), 0.5, c_border),
            ('TOPPADDING', (0,0), (-1,-1), 4),
            ('BOTTOMPADDING', (0,0), (-1,-1), 4),
            ('LEFTPADDING', (0,0), (-1,-1), 7),
            ('RIGHTPADDING', (0,0), (-1,-1), 7),
        ]))
        return t

    # Section 1: Choix & Compréhension
    sec1 = make_section(
        "1. Choix du Sujet & Compréhension du Besoin Métier",
        [
            "Le <b>Sujet 3 (Covoiturage régional interurbain)</b> répond à un défi logistique et économique majeur au Maroc : optimiser les mobilités transversales (Agadir, Marrakech, Essaouira, Casablanca). Les solutions actuelles échouent à gérer efficacement les arrêts intermédiaires.",
            "<b>Besoin central résolu :</b> Permettre à un conducteur de publier un itinéraire multi-escales et autoriser les passagers à réserver n'importe quel sous-tronçon. Dès qu'un passager libère un siège à une escale (ex. Essaouira), ce siège est <b>immédiatement remis en vente</b> pour l'étape suivante (Essaouira $\\rightarrow$ Marrakech), maximisant l'occupation du véhicule et rentabilisant les frais autoroutiers dans le respect strict du barème légal non commercial (plafond 0,65 DH/km)."
        ]
    )
    story.append(sec1)
    story.append(Spacer(1, 4))

    # Section 2: Décomposition & Arbitrages
    sec2 = make_section(
        "2. Décomposition du Problème & Arbitrages d'Architecture",
        [
            "<b>• Modélisation Atomique par Tronçons (ADR-003) :</b> Décomposition d'un trajet en une chaîne ordonnée de <code>TripStop</code> et de sous-segments adjacents <code>TripSegment</code>. La disponibilité d'une réservation entre les arrêts <i>i</i> et <i>j</i> est le goulot d'étranglement exact : <i>Places = min(availableSeats<sub>k</sub>)</i> pour <i>k &isin; [i, j-1]</i>. L'incrémentation/décrémentation est strictement ciblée sur les segments traversés.",
            "<b>• Gestion de la Concurrence Sévère (ADR-002) :</b> Choix délibéré d'un <b>verrouillage pessimiste</b> (<code>LockMode::PESSIMISTIC_WRITE</code> via <code>SELECT ... FOR UPDATE</code>) ordonné selon Dijkstra (<code>segmentOrder ASC</code>) combiné à <code>Query::HINT_REFRESH</code>. Contrairement au verrouillage optimiste qui rejetterait l'utilisateur avec une erreur 409 lors des pics d'achat, le verrou pessimiste sérialise proprement les réservations et élimine tout risque de surréservation (overbooking) ou de blocage mutuel (deadlock).",
            "<b>• Architecture de Sécurité Senior :</b> 2 rôles techniques socles (<code>ROLE_USER</code>, <code>ROLE_ADMIN</code>) combinés à 3 Symfony Voters contextuels (<code>TripVoter</code>, <code>BookingVoter</code>, <code>ReviewVoter</code>). Cela satisfait avec élégance la règle métier <i>« un utilisateur peut être conducteur sur un trajet et passager sur un autre »</i> sans duplication de tables ni commutation de session.",
            "<b>• Résilience SIG OSRM / Nominatim (ADR-004) :</b> Cache PSR-6 (30 jours) + dictionnaire/matrice de secours hors-ligne (A3, A7, N1) garantissant un fonctionnement 100% autonome et sans panne même en cas de coupure réseau ou de limitation d'API externe."
        ]
    )
    story.append(sec2)
    story.append(Spacer(1, 4))

    # Section 3: Gestion du Temps
    sec3 = make_section(
        "3. Ordre de Réalisation & Gestion du Temps (Méthode Agile 7 Jours)",
        [
            "<b>• Sprint 0-1 (J1-J2) :</b> Socle Docker multi-conteneurs, modélisation relationnelle Doctrine, migrations et Back-Office EasyAdmin 4.",
            "<b>• Sprint 2 (J3) :</b> Moteur de réservation transactionnel, isolation ACID, politique d'annulation et suite de tests unitaires (PHPUnit).",
            "<b>• Sprint 3 (J4-J5) :</b> Parcours complet des 11 écrans UX/UI en Tailwind CSS/Stimulus, intégration des icônes vectorielles Lucide et dynamisation 100% BDD.",
            "<b>• Sprint 4-5 (J6-J7) :</b> Intégration cartographique Leaflet/OSRM, algorithme tarifaire équitable, conteneurisation clé en main (Niveau 5) et traces IA."
        ]
    )
    story.append(sec3)
    story.append(Spacer(1, 4))

    # Section 4: Utilisation de l'IA
    sec4 = make_section(
        "4. Utilisation de l'IA (Succès, Limites & Arbitrages de l'Ingénieur)",
        [
            "<b>• Ce qui a parfaitement fonctionné :</b> L'utilisation combinée de <b>Google AI Studio (Gemini 2.5 / Hydrogen)</b> pour le brainstorming architectural et d'<b>Antigravity</b> pour l'implémentation a permis un gain de temps d'environ <b>65%</b> sur l'échafaudage des contrôleurs, entités, DTOs et jeux de tests unitaires.",
            "<b>• Hallucinations & Corrections par l'Ingénieur :</b>",
            "  - <i>Concurrence :</i> L'IA préconisait initialement un verrouillage optimiste avec <code>@Version</code>. Face aux exigences d'expérience utilisateur lors de rushs de réservation, j'ai imposé le modèle pessimiste avec réévaluation immédiate du cache Doctrine (<code>HINT_REFRESH</code>).",
            "  - <i>Dépendance externe :</i> L'IA générait des appels OSRM/Nominatim synchrones sans tolérance de panne. J'ai conçu la couche de cache et la matrice de contingence locale marocaine pour garantir une haute disponibilité permanente lors de l'évaluation du jury.",
            "  - <i>Qualité UI :</i> Refus des suggestions d'emojis informels ou de termes commerciaux inappropriés (ex. « haut de gamme ») au profit d'un design system épuré sous Symfony UX Icons (Lucide) et d'un lexique conforme au transport solidaire."
        ]
    )
    story.append(sec4)
    story.append(Spacer(1, 4))

    # Section 5: Roadmap +1 Semaine
    sec5 = make_section(
        "5. Perspectives d'Évolution avec 1 Semaine Supplémentaire",
        [
            "<b>1. Passerelle Monétique Marocaine :</b> Intégration du SDK CMI / PayZone avec authentification 3D-Secure et séquestre bancaire automatisé.",
            "<b>2. Dynamic Route Matching :</b> Algorithme de calcul de détour (max 5 km / 10 min) pour proposer des points de ramassage optimisés sur autoroute.",
            "<b>3. Application Mobile Compagnon :</b> Client Flutter iOS/Android pour géolocalisation live du véhicule et notifications push Firebase."
        ]
    )
    story.append(sec5)
    story.append(Spacer(1, 4))

    # Footer note
    story.append(Paragraph(
        "<i>Plateforme SafarCar Maroc · Code source : github.com/Moaad-Daoudi/emc-pfe-covoiturage · 31 Tests Unitaires Validés (100% OK)</i>",
        ParagraphStyle('Footer', fontName='Helvetica-Oblique', fontSize=6.5, leading=7.5, textColor=c_muted, alignment=1)
    ))

    doc.build(story)
    print(f"[OK] Generated {filename} successfully.")

if __name__ == "__main__":
    out_pdf = "note_conceptuelle.pdf"
    if len(sys.argv) > 1:
        out_pdf = sys.argv[1]
    build_pdf(out_pdf)
