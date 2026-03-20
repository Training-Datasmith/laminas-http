# Architecture: laminas-http

## Purpose

laminas-http provides a full HTTP client and message abstraction layer, including:
- An HTTP client (`Client`) that supports multiple transport adapters
- Request and Response message objects following RFC 7230/7231
- A rich set of typed header objects (Accept, Authorization, Set-Cookie, etc.)
- Header value validation with CRLF-injection (HTTP response splitting) protection

## Directory Structure

```
src/
  Abstract_Message.php        # Base class for Request and Response; holds headers + version
  Request.php                 # HTTP request message (method, URL, body, headers)
  Response.php                # HTTP response message (status code, reason, body, headers)
  Headers.php                 # Container for a collection of Header objects
  Header_Loader.php           # Maps header names to concrete header classes
  Cookies.php                 # Cookie jar for the HTTP client
  Client.php                  # Main HTTP client; dispatches requests via an adapter
  Client_Static.php           # Static façade over Client for convenience
  Header/
    Header_Interface.php      # Contract for all header objects
    Header_Value.php          # Security utility: CRLF/injection filter and validator
    Generic_Header.php        # General-purpose header implementation
    Generic_Multi_Header.php  # Header allowing multiple values
    Abstract_Accept.php       # Base for content-negotiation headers (Accept, Accept-*)
    Abstract_Date.php         # Base for date-valued headers
    Abstract_Location.php     # Base for URL-valued headers
    Set_Cookie.php            # Typed Set-Cookie header with all RFC attributes
    [50+ typed header classes] # One class per standard HTTP header
  Client/
    Adapter/
      Adapter_Interface.php   # Contract for transport adapters
      Socket.php              # Default TCP/socket adapter
      Curl.php                # cURL-based adapter
      Proxy.php               # Proxy-tunnelling adapter
      Test.php                # In-memory test adapter
  PhpEnvironment/
    Request.php               # Adapter that reads the current PHP SAPI request
    Response.php              # Adapter that sends headers via PHP header()
  Exception/                  # Domain exception hierarchy
test/
  [mirrors src/ structure]    # PHPUnit test suite
```

## Key Design Decisions

- **Typed headers**: Each standard header has its own class rather than using raw
  strings. This prevents injection at the class boundary and enables IDE support.
- **Header value security**: `Header_Value::filter()` and `is_valid()` strip/reject
  CR, LF, NUL, and DEL characters to prevent HTTP response splitting attacks.
- **Adapter pattern**: `Client` is decoupled from the transport. Swap `Socket` for
  `Curl` or inject `Test` for unit-testing without real HTTP connections.
- **Lazy header parsing**: `Abstract_Message` defers header parsing until first access,
  so constructing a message from a raw string is cheap.
- **Static client façade**: `Client_Static` delegates to a shared `Client` instance
  for simple use cases without dependency injection.

## Extension Points

- Implement `Client\Adapter\Adapter_Interface` to add a new HTTP transport (e.g. Guzzle).
- Implement `Header\Header_Interface` to add a custom typed header.
- Extend `Abstract_Message` to create protocol-specific message variants.

## Dependency Flow

```
Client → Adapter (Socket | Curl | Proxy | Test)
Client → Request → Abstract_Message → Headers → Header_Interface implementations
Client → Response → Abstract_Message
Headers → Header_Loader → typed header classes
Header_Value ← Generic_Header (validates all set/parsed values)
```
