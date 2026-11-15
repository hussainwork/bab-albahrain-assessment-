import 'package:flutter/foundation.dart' show kIsWeb;

/// Address of the PHP checkout. Override at build or run time:
///   flutter run --dart-define=CHECKOUT_URL=https://your-tunnel.trycloudflare.com/
/// The default is how an Android emulator reaches the PC running `php -S localhost:8000`.
const String checkoutUrl = String.fromEnvironment(
  'CHECKOUT_URL',
  defaultValue: kIsWeb ? 'http://localhost:8000/' : 'http://10.0.2.2:8000/',
);
