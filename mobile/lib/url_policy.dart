/// Decides which pages the in-app browser may open, so a stray link cannot
/// take the customer away from the shop or the payment page.
class UrlPolicy {
  UrlPolicy(Uri home) : _homeHost = home.host;

  final String _homeHost;

  bool allows(Uri uri) {
    if (uri.scheme == 'about' || uri.scheme == 'data') {
      return true;
    }
    if (uri.scheme != 'https' && uri.scheme != 'http') {
      return false;
    }

    final host = uri.host;
    return host == _homeHost || host == 'gate-e.net' || host.endsWith('.gate-e.net');
  }
}
