{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>{{ route('home') }}</loc>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>{{ route('explore.tutors') }}</loc>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>{{ route('auth.register_parent') }}</loc>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>{{ route('auth.register_tutor') }}</loc>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>{{ route('contract') }}</loc>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
  <url>
    <loc>{{ route('privacy') }}</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  @foreach($areas as $area)
  <url>
    <loc>{{ route('tutors.landing', \Illuminate\Support\Str::slug($area->name)) }}</loc>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
  @endforeach
  @foreach($subjects as $subject)
  <url>
    <loc>{{ route('tutors.landing', \Illuminate\Support\Str::slug($subject->name)) }}</loc>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
  @endforeach
</urlset>
