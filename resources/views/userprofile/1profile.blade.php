<!DOCTYPE html>
<html>
<head>
<style>
.card {
  box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2);
  max-width: 300px;
  margin: auto;
  text-align: center;
  font-family: arial;
  border: 1px solid rgb(196, 196, 196);
  border-radius: 10px;
  background-color:rgb(237, 236, 235);
}
.title {
  color: grey;
  font-size: 18px;
}
button {
  border: none;
  outline: 0;
  display: inline-block;
  padding: 8px;
  color: white;
  background-color: #000;
  text-align: center;
  cursor: pointer;
  width: 100%;
  font-size: 18px;
}
a {
  text-decoration: none;
  font-size: 22px;
  color: black;
}
button:hover, a:hover {
  opacity: 0.7;
}
</style>
</head>
<body>
<h2 style="text-align:center;font-size:30px;font-weight:bold; text-transform:uppercase;color:rgb(31, 31, 214)">BICS-2023, ALGERIA</h2>
<h4 style="text-align:center;font-size:16px;text-transform: uppercase;color:rgb(5, 161, 117)">Entry Pass</h4>
<div class="card">
  <img src="{{ public_path($data->qr_img_path) }}" style="width:100%">
  <h1>{{ $data->firstname.' '.$data->lastname }}</h1>
  <p class="title">{{ $data->phone }}</p>
  <p class="title">{{ $data->profession }} </p>
  <p class="title">{{ $data->country }} </p>
  <p><button>{{ $data->qr_code }}</button></p>
</div>
</body>
</html>
