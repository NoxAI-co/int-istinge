<?php

namespace App\Services;

/**
 * Clasifica los mensajes de error que devuelve Meta/WhatsApp cuando un envío
 * queda en status = failed, para decidir qué hacer con el documento asociado
 * (factura / ingreso).
 *
 * Existen tres desenlaces posibles:
 *
 *  - RECIPIENT  El número del destinatario no sirve ("Message undeliverable").
 *               Se re-arma el envío (whatsapp = 0) y se incrementa
 *               cont_message_undeliverable; al llegar a 3 se deja de intentar y
 *               la UI avisa que el cliente probablemente no tiene WhatsApp.
 *
 *  - SENDER     El fallo es de la cuenta emisora, no del cliente: problema de
 *               pago de la cuenta de WhatsApp Business, token/API key inválida,
 *               rate-limit, plantilla rechazada, etc. El mensaje NO se entregó,
 *               así que también se re-arma el envío (whatsapp = 0), pero el
 *               contador que se incrementa es cont_envio_fallido — marcar el
 *               número del cliente como inválido sería incorrecto y le
 *               bloquearía el envío manual.
 *
 *  - AMBIGUOUS  Meta reporta un fallo pero el mensaje pudo haberse entregado
 *               igual (típicamente el filtro de "healthy ecosystem
 *               engagement"). No se toca nada: reenviar produciría mensajes
 *               duplicados al cliente.
 */
class WhatsappFailureClassifier
{
    /** El número del destinatario no tiene WhatsApp / no es alcanzable. */
    const RECIPIENT = 'recipient';

    /** El envío falló por la cuenta emisora; el mensaje no salió. */
    const SENDER = 'sender';

    /** El mensaje pudo haberse entregado pese al fallo reportado. */
    const AMBIGUOUS = 'ambiguous';

    /** Tope de reintentos para fallos de destinatario. */
    const MAX_RECIPIENT_RETRIES = 3;

    /**
     * Tope de reintentos para fallos de la cuenta emisora. Es más alto que el
     * de destinatario porque estos fallos suelen resolverse solos (se paga la
     * cuenta, se renueva el token) y el documento debe alcanzar a salir, pero
     * sigue acotado para no reenviar en bucle cada 15 min indefinidamente.
     */
    const MAX_SENDER_RETRIES = 5;

    /**
     * Errores donde Meta puede haber entregado el mensaje aunque marque failed.
     */
    private static $ambiguousPatterns = [
        'This message was not delivered to maintain healthy ecosystem engagement',
    ];

    /**
     * Errores que apuntan al número del destinatario.
     */
    private static $recipientPatterns = [
        'Message undeliverable',
        '131026',
        'Recipient phone number not in allowed list',
        'not a valid whatsapp user',
    ];

    /**
     * Clasifica un mensaje de error de Meta.
     *
     * @param  string|null $errorMessage
     * @return string  Uno de RECIPIENT | SENDER | AMBIGUOUS
     */
    public static function classify($errorMessage)
    {
        $error = trim((string) $errorMessage);

        // Sin detalle del error no podemos afirmar que el mensaje no salió;
        // tratarlo como ambiguo evita reenvíos a ciegas.
        if ($error === '') {
            return self::AMBIGUOUS;
        }

        foreach (self::$ambiguousPatterns as $pattern) {
            if (stripos($error, $pattern) !== false) {
                return self::AMBIGUOUS;
            }
        }

        foreach (self::$recipientPatterns as $pattern) {
            if (stripos($error, $pattern) !== false) {
                return self::RECIPIENT;
            }
        }

        // Cualquier otro fallo reportado por Meta (Business eligibility payment
        // issue, API Key is not enabled, rate limit, plantilla inexistente…)
        // significa que el mensaje no llegó a enviarse: se reintenta.
        return self::SENDER;
    }

    /**
     * Nombre de la columna contadora que corresponde a la clasificación.
     *
     * @param  string $classification
     * @return string|null
     */
    public static function counterColumn($classification)
    {
        if ($classification === self::RECIPIENT) {
            return 'cont_message_undeliverable';
        }

        if ($classification === self::SENDER) {
            return 'cont_envio_fallido';
        }

        return null;
    }

    /**
     * Tope de reintentos que corresponde a la clasificación.
     *
     * @param  string $classification
     * @return int|null
     */
    public static function maxRetries($classification)
    {
        if ($classification === self::RECIPIENT) {
            return self::MAX_RECIPIENT_RETRIES;
        }

        if ($classification === self::SENDER) {
            return self::MAX_SENDER_RETRIES;
        }

        return null;
    }
}
