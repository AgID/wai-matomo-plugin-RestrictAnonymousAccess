# Matomo RestrictAnonymousAccess Plugin

## Description

This plugin restricts the access of the anonymous user to a
specified list of requests. The allowed requests can be specified in two
different forms:

- as a query string that must be contained in the URL of the request
 (`allowed_requests[]` array);
- as a query string that must be contained in the http `Referer` header of the
  request (`allowed_referrers[]` array);

The order in which he different parameters are specified in the query string
is irrelevant.

The specified options are considered with OR logic, so it only needs one
condition to be met to allow the request.

Optionally the anonymous user can be redirected to a custom URL.

Note that the following query strings are always allowed:

```ini
allowed_requests[] = "module=Login"
allowed_requests[] = "module=Proxy&action=getCss"
allowed_requests[] = "module=Proxy&action=getCoreJs"
allowed_requests[] = "module=Proxy&action=getNonCoreJs"
```

## Installation

Refer to [this Matamo FAQ](https://matomo.org/faq/plugins/faq_21/).

## Usage

Add the following section to your `config.ini.php` according to your needs:

```ini
[RestrictAnonymousAccess]
allowed_requests[] = "module=Module&param=value"
allowed_requests[] = "module=OtherModule"
allowed_referrers[] = "module=Module&action=action&param=value"
; uncomment to redirect the user instead of displaying an error page
;redirect_unallowed_to = <URL>
```
