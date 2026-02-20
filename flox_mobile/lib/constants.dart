class AppConstants {
  static const String serverIp = '82.208.23.150';
  static const String baseUrl = 'http://$serverIp/FloxWatch';
  static const String wsUrl = 'ws://$serverIp:8080';

  static String sanitizeUrl(String? url) {
    if (url == null || url.isEmpty) return '';
    if (url.startsWith('http')) return url;

    String cleanUrl = url;
    // Remove all occurrences of ../
    cleanUrl = cleanUrl.replaceAll('../', '');
    // Remove leading slash if present
    if (cleanUrl.startsWith('/')) {
      cleanUrl = cleanUrl.substring(1);
    }

    return '$baseUrl/$cleanUrl';
  }
}
