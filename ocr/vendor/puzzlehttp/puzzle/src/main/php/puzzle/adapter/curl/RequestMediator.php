<?php

/**
 * Mediator between curl handles and request objects
 */
class puzzle_adapter_curl_RequestMediator
{
    /** @var puzzle_adapter_TransactionInterface */
    private $transaction;
    /** @var puzzle_message_MessageFactoryInterface */
    private $messageFactory;
    private $statusCode;
    private $reasonPhrase;
    private $body;
    private $protocolVersion;
    private $headers;

    /**
     * @param puzzle_adapter_TransactionInterface    $transaction    Transaction to populate
     * @param puzzle_message_MessageFactoryInterface $messageFactory Creates responses
     */
    public function __construct(
        puzzle_adapter_TransactionInterface $transaction,
        puzzle_message_MessageFactoryInterface $messageFactory
    ) {
        $this->transaction = $transaction;
        $this->messageFactory = $messageFactory;
    }

    /**
     * Set the body that will hold the response body
     *
     * @param puzzle_stream_StreamInterface $body Response body
     */
    public function setResponseBody(puzzle_stream_StreamInterface $body = null)
    {
        $this->body = $body;
    }

    /**
     * Receive a response header from curl
     *
     * @param resource $curl   Curl handle
     * @param string   $header Received header
     *
     * @return int
     */
    public function receiveResponseHeader($curl, $header)
    {
        static $normalize = array("\r", "\n");
        $length = strlen($header);
        $header = str_replace($normalize, '', $header);

        if (strpos($header, 'HTTP/') === 0) {
            $startLine = explode(' ', $header, 3);
            // Only download the body to a target body when a successful
            // response is received.
            if ($startLine[1][0] != '2') {
                $this->body = null;
            }
            $this->statusCode = $startLine[1];
            $this->reasonPhrase = isset($startLine[2]) ? $startLine[2] : null;
            $this->protocolVersion = substr($startLine[0], -3);
            $this->headers = array();
        } elseif ($pos = strpos($header, ':')) {
            $this->headers[substr($header, 0, $pos)][] = substr($header, $pos + 1);
        } elseif ($header == '' && $this->statusCode >= 200) {
            $response = $this->messageFactory->createResponse(
                $this->statusCode,
                $this->headers,
                $this->body,
                array(
                    'protocol_version' => $this->protocolVersion,
                    'reason_phrase'    => $this->reasonPhrase
                )
            );
            $this->headers = $this->body = null;
            $this->transaction->setResponse($response);
            // Allows events to react before downloading any of the body
            puzzle_event_RequestEvents::emitHeaders($this->transaction);
        }

        return $length;
    }

    /**
     * Write data to the response body of a request
     *
     * @param resource $curl
     * @param string   $write
     *
     * @return int
     */
    public function writeResponseBody($curl, $write)
    {
        if (!($response = $this->transaction->getResponse())) {
            return 0;
        }

        // Add a default body on the response if one was not found
        if (!($body = $response->getBody())) {
            $body = new puzzle_stream_Stream(fopen('php://temp', 'r+'));
            $response->setBody($body);
        }

        return $body->write($write);
    }

    /**
     * Read data from the request body and send it to curl
     *
     * @param resource $ch     Curl handle
     * @param resource $fd     File descriptor
     * @param int      $length Amount of data to read
     *
     * @return string
     */
    public function readRequestBody($ch, $fd, $length)
    {
        return (string) $this->transaction->getRequest()->getBody()->read($length);
    }
}
