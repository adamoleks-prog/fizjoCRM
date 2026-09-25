<?php

namespace App\Enums;

enum PatientAccessAction: string
{
    case Viewed = 'viewed';
    case Updated = 'updated';
    case DocumentDownloaded = 'document_downloaded';
    case SentToAi = 'sent_to_ai';
    case VisitCardDownloaded = 'visit_card_downloaded';
    case VisitCardSent = 'visit_card_sent';
    case ConsentSigned = 'consent_signed';
}
