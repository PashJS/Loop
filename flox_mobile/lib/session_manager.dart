import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'constants.dart';

class SessionManager {
  static final SessionManager _instance = SessionManager._internal();
  factory SessionManager() => _instance;
  SessionManager._internal();

  String? _cookie;
  String? _sessionId;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    _cookie = prefs.getString('session_cookie');
    _sessionId = prefs.getString('session_id');
  }

  Future<void> saveCookie(String? rawCookie) async {
    if (rawCookie == null) return;
    final index = rawCookie.indexOf(';');
    final cookie = (index == -1) ? rawCookie : rawCookie.substring(0, index);
    _cookie = cookie;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('session_cookie', cookie);
  }

  Future<void> saveSessionId(String? id) async {
    if (id == null) return;
    _sessionId = id;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('session_id', id);
  }

  String? get getSessionId => _sessionId;

  Future<void> clearSession() async {
    _cookie = null;
    _sessionId = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('session_cookie');
    await prefs.remove('session_id');
    await prefs.remove('auth_user');
  }

  Map<String, String> get headers {
    final Map<String, String> h = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (_cookie != null) {
      h['Cookie'] = _cookie!;
    }
    return h;
  }

  // Wrapper for GET requests
  Future<http.Response> get(String endpoint) async {
    final url = Uri.parse('${AppConstants.baseUrl}$endpoint');
    return http.get(url, headers: headers);
  }

  // Wrapper for POST requests
  Future<http.Response> post(String endpoint, Map<String, dynamic> body) async {
    final url = Uri.parse('${AppConstants.baseUrl}$endpoint');
    return http.post(url, headers: headers, body: jsonEncode(body));
  }
}
