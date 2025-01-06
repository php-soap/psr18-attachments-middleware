<?php

namespace Soap\Psr18AttachmentMiddleware\Multipart;

enum TransportType : string
{
    case Swa = 'https://www.w3.org/TR/SOAP-attachments/';
    case Mtom = 'https://www.w3.org/TR/soap12-mtom/';
}
