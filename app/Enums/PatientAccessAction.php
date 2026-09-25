<?php

namespace App\Enums;

enum PatientAccessAction: string
{
    case Viewed = 'viewed';
    case Updated = 'updated';
    case DocumentDownloaded = 'document_downloaded';
    case SentToAi = 'sent_to_ai';
}
