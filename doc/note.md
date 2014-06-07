Lập trình:
Ưu tiên group trước, page sau, cuối cùng là forum.

Cơ bản về sử dụng graph api
https://developers.facebook.com/docs/graph-api/using-graph-api/
https://developers.facebook.com/blog/post/478/
Liên quan đến group
https://developers.facebook.com/docs/reference/api/group/

Nâng cao
https://developers.facebook.com/docs/reference/fql

Truy vấn dữ liệu cũ:
http://stackoverflow.com/a/8979457

List Facebook groups with its ID
select gid, name from group where gid in (select gid from group_member where uid = $uid