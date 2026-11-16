
# PaySuite for OpenCart

Official PaySuite payment gateway integration for OpenCart 4.x.

## Overview

PaySuite for OpenCart is a payment gateway extension developed by HYPERTECH, LDA that enables online stores running OpenCart 4.x to integrate PaySuite as a payment method.

The extension provides an integration between OpenCart and the PaySuite payment gateway, allowing merchants to accept payments through their online stores.

## Features

- PaySuite payment gateway integration.
- Administrative configuration through the OpenCart dashboard.
- Integration with the OpenCart checkout process.
- API communication with PaySuite.
- Payment processing integration.
- Payment configuration management.

> Features are subject to the functionality implemented in the current release.

## Requirements

- OpenCart 4.x.
- PHP version compatible with your OpenCart installation.
- Active PaySuite account.
- Valid PaySuite API credentials.
- HTTPS-enabled online store.

## Installation

1. Download the latest installation package from the [GitHub Releases](https://github.com/LazaroMagaia/opencart-paysuite/releases) page.
2. Log in to your OpenCart administration panel.
3. Navigate to Extensions → Installer.
4. Upload the PaySuite installation package.
5. Navigate to Extensions → Extensions.
6. Select the Payments extension category.
7. Locate PaySuite and click Install.
8. Open the extension configuration page.
9. Enter your PaySuite integration credentials.
10. Configure the available settings and enable the payment method.
11. Save your changes.

Note: Menu labels may vary depending on your OpenCart installation.

## Configuration

After installing the extension:

1. Access the OpenCart administration panel.
2. Navigate to Extensions → Extensions → Payments.
3. Select PaySuite.
4. Enter the required API credentials.
5. Configure the available payment options.
6. Set the payment method status to Enabled.
7. Save the configuration.

Make sure your PaySuite credentials are valid before enabling the payment method in production.

## Security

Security is an important part of payment integration.

- Never expose API credentials or private keys.
- Do not commit sensitive configuration files to GitHub.
- Always use HTTPS.
- Validate payment notifications on the server side.
- Verify payment responses before updating order statuses.
- Never trust payment information received directly from the browser.
- Avoid storing sensitive payment information in application logs.

## Project Structure

```text
opencart-paysuite/
├── admin/
├── catalog/
├── install.json
├── README.md
├── LICENSE
├── CHANGELOG.md
└── .gitignore
```

## Compatibility

| Component | Compatibility |
|---|---|
| OpenCart | 4.x |
| PHP | According to OpenCart requirements |
| Payment Gateway | PaySuite |
| Extension Type | Payment Extension |

## Versioning

This project follows Semantic Versioning.

Version format:

`MAJOR.MINOR.PATCH`

Examples:

- `1.0.0` - Initial release.
- `1.0.1` - Bug fixes.
- `1.1.0` - New features.
- `2.0.0` - Major changes or breaking updates.

## Releases

Official releases and installation packages are available on GitHub:

[PaySuite for OpenCart Releases](https://github.com/LazaroMagaia/opencart-paysuite/releases)

For a detailed list of changes, see [CHANGELOG.md](CHANGELOG.md).

## Bug Reports

If you encounter an issue, please open a GitHub Issue:

[Report a Bug](https://github.com/LazaroMagaia/opencart-paysuite/issues)

When reporting an issue, include:

- OpenCart version.
- PHP version.
- PaySuite extension version.
- Description of the issue.
- Steps to reproduce the issue.
- Relevant error messages (without sensitive information).

Never share API keys, passwords or private credentials in public issues.

## Support

For commercial inquiries, licensing or technical support, please visit:

https://paysuite.co.mz

## Author

**HYPERTECH, LDA**

Software Development & Technology Solutions

Website: https://paysuite.co.mz

## License

PaySuite for OpenCart is proprietary software.

All rights reserved by HYPERTECH, LDA.

See the [LICENSE](LICENSE) file for details.
