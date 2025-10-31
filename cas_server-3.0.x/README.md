# Central Authentication System (CAS) Server 3.0.x

Use Drupal users table as a Central Authentication System (CAS) Server.

Do NOT simultaneously enable the CAS and CAS Server modules on the same site.
Unpredictable errors may occur.

## Requirements

This module requires no modules outside of Drupal core 10.3 or 11.

### SSL:
The CAS protocol requires the CAS server to run over HTTPS (not HTTP).
Your Drupal site will need to be configured for HTTPS separately. The site
should also have a valid SSL certificate signed by a trusted Certificate
Authority. The certificate should be made available to your CAS clients
for additional security.

### Upgrading from 2.x

The 3.0.x branch moves numerous interface and class files while keeping their
names the same. This will confuse PHP if any kind of caching is present: APC,
APCu, Redis, etc. Composer autoload files should also be regenerated. If Drupal
complains about missing files or interfaces; Flush everything, restart
everything; caches, web service, php-fpm service.

## Installation

Install as you would normally install a contributed Drupal module. For
further information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

At least one Service definition must be created for a site to operate as CAS
Server.

A Service definition consists of:
- Label & Machine name
- Service URL pattern; Used to match against provided service urls to
  determine which to use.
- SSO flag; if this service is used is SSO or just single instance
  authentication. Such a service will authenticate but not start an SSO
  session.
- Attributes to release to the service on successful authentication which
  can be used by the service to eg. create a new user. See below for
  related modules.
- Access permissions (optional) which determine which roles are permitted
  to use a service.

To create a Service definition, log in as an admin user with 'administer site
configuration' permission:

> Configuration -> People -> Cas Server -> Add/Edit Service definitions

Common settings for the CAS Server and all Services can be set here:

> Configuration -> People -> Cas Server

## Related modules

[CAS](https://www.drupal.org/project/cas)

>>>
This module provide single sign-on (SSO) capability for your Drupal site by
implementing the CAS protocol.

When using this module, local Drupal user accounts are still used, but the
authentication process is not handled by Drupal's standard login form.
Instead, users are redirected to your institution's CAS server to collect
credentials. Your Drupal site just receives the username (and optionally some
other attributes) from the CAS server after a successful authentication.
>>>

[CAS Attributes](https://drupal.org/project/cas_attributes)

>>>
This module allows you to assign user field values (text fields only) and user
roles based on attributes received from your CAS server during authentication.

It also exposes CAS attributes as tokens. One example where this is useful is
if you have a webform and you want to pre-fill certain webform fields with
attribute values from CAS (like name and email).
>>>

## More information

[Project page](https://www.drupal.org/project/cas_server)

[Issue tracker](https://www.drupal.org/project/issues/cas_server)
