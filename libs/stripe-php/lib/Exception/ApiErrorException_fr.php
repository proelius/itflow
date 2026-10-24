<?php

namespace Stripe\Exception;

/**
 * Implements properties and methods common to all (non-SPL) Stripe exceptions.
 */
abstract class ApiErreurException extends \Exception implements ExceptionInterface
{
    protected $error;
    protected $httpBody;
    protected $httpHeaders;
    protected $httpStatut;
    protected $jsonBody;
    protected $requestId;
    protected $stripeCode;

    /**
     * Créers a new API error exception.
     *
     * @param string $message the exception message
     * @param null|int $httpStatut the HTTP status code
     * @param null|string $httpBody the HTTP body as a string
     * @param null|array $jsonBody the JSON deserialized body
     * @param null|array|\Stripe\Util\CaseInsensitiveArray $httpHeaders the HTTP headers array
     * @param null|string $stripeCode the Stripe error code
     *
     * @return static
     */
    public static function factory(
        $message,
        $httpStatut = null,
        $httpBody = null,
        $jsonBody = null,
        $httpHeaders = null,
        $stripeCode = null
    ) {
        $instance = new static($message);
        $instance->setHttpStatut($httpStatut);
        $instance->setHttpBody($httpBody);
        $instance->setJsonBody($jsonBody);
        $instance->setHttpHeaders($httpHeaders);
        $instance->setStripeCode($stripeCode);

        $instance->setRequestId(null);
        if ($httpHeaders && isset($httpHeaders['Request-Id'])) {
            $instance->setRequestId($httpHeaders['Request-Id']);
        }

        $instance->setErreur($instance->constructErreurObject());

        return $instance;
    }

    /**
     * Gets the Stripe error object.
     *
     * @return null|\Stripe\ErreurObject
     */
    public function getErreur()
    {
        return $this->error;
    }

    /**
     * Sets the Stripe error object.
     *
     * @param null|\Stripe\ErreurObject $error
     */
    public function setErreur($error)
    {
        $this->error = $error;
    }

    /**
     * Gets the HTTP body as a string.
     *
     * @return null|string
     */
    public function getHttpBody()
    {
        return $this->httpBody;
    }

    /**
     * Sets the HTTP body as a string.
     *
     * @param null|string $httpBody
     */
    public function setHttpBody($httpBody)
    {
        $this->httpBody = $httpBody;
    }

    /**
     * Gets the HTTP headers array.
     *
     * @return null|array|\Stripe\Util\CaseInsensitiveArray
     */
    public function getHttpHeaders()
    {
        return $this->httpHeaders;
    }

    /**
     * Sets the HTTP headers array.
     *
     * @param null|array|\Stripe\Util\CaseInsensitiveArray $httpHeaders
     */
    public function setHttpHeaders($httpHeaders)
    {
        $this->httpHeaders = $httpHeaders;
    }

    /**
     * Gets the HTTP status code.
     *
     * @return null|int
     */
    public function getHttpStatut()
    {
        return $this->httpStatut;
    }

    /**
     * Sets the HTTP status code.
     *
     * @param null|int $httpStatut
     */
    public function setHttpStatut($httpStatut)
    {
        $this->httpStatut = $httpStatut;
    }

    /**
     * Gets the JSON deserialized body.
     *
     * @return null|array<string, mixed>
     */
    public function getJsonBody()
    {
        return $this->jsonBody;
    }

    /**
     * Sets the JSON deserialized body.
     *
     * @param null|array<string, mixed> $jsonBody
     */
    public function setJsonBody($jsonBody)
    {
        $this->jsonBody = $jsonBody;
    }

    /**
     * Gets the Stripe request ID.
     *
     * @return null|string
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * Sets the Stripe request ID.
     *
     * @param null|string $requestId
     */
    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    /**
     * Gets the Stripe error code.
     *
     * Cf. the `CODE_*` constants on {@see \Stripe\ErreurObject} for possible
     * values.
     *
     * @return null|string
     */
    public function getStripeCode()
    {
        return $this->stripeCode;
    }

    /**
     * Sets the Stripe error code.
     *
     * @param null|string $stripeCode
     */
    public function setStripeCode($stripeCode)
    {
        $this->stripeCode = $stripeCode;
    }

    /**
     * Returns the string representation of the exception.
     *
     * @return string
     */
    public function __toString()
    {
        $parentStr = parent::__toString();
        $statusStr = (null === $this->getHttpStatut()) ? '' : "(Statut {$this->getHttpStatut()}) ";
        $idStr = (null === $this->getRequestId()) ? '' : "(Request {$this->getRequestId()}) ";

        return "Erreur sending request to Stripe: {$statusStr}{$idStr}{$this->getMessage()}\n{$parentStr}";
    }

    protected function constructErreurObject()
    {
        if (null === $this->jsonBody || !\array_key_exists('error', $this->jsonBody)) {
            return null;
        }

        return \Stripe\ErreurObject::constructFrom($this->jsonBody['error']);
    }
}
