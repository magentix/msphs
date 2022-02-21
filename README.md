# MSPHS

A minimal secure HTTP Server in PHP.

## About

This software is only able to display a static site. It can be used for educational purposes:

- Work with Socket in PHP
- Establish a secure connection with TLS 1.3
- Learn more about HTTP status and headers

## Requirements

- PHP >= 8.0
- OpenSSL >= 1.1.1

## Files

- **www:** the static website directory
- **server.php**: the script to run
- **cert.pem**: the certificate full chain (generated)
- **key.pem**: the certificate private key (generated)

## Generate the certificate

### Local

Self-signed certificate:

```bash
openssl req -nodes -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365
```

### Live

Let's Encrypt certificate:

```bash
apt-get install certbot
certbot certonly --standalone -d example.com --staple-ocsp -m hello@example.com --agree-tos
ln -s /etc/letsencrypt/live/example.com/fullchain.pem cert.pem
ln -s /etc/letsencrypt/live/example.com/privkey.pem key.pem
```

## Start the server

```bash
php server.php &
```

## Notes

On any secure connection, the TLS handshake takes between 10 and 20ms.